import createClient, { type Middleware } from "openapi-fetch";

import type { components, paths } from "./schema";
import { ulid } from "./ulid";

/** The RFC 9457 error body every non-2xx response carries. */
export type Problem = components["schemas"]["Problem"];

export const IDEMPOTENCY_KEY_HEADER = "Idempotency-Key";
export const REQUEST_ID_HEADER = "X-Request-Id";

/** Methods the API treats as writes and therefore requires a key for. */
const WRITE_METHODS = new Set(["POST", "PUT", "PATCH", "DELETE"]);

export interface ApiClientOptions {
  /** Base URL including the version segment, e.g. https://api.example.com/api/v1 */
  baseUrl?: string;
  /**
   * Correlation id to propagate. On the server this is the id of the inbound
   * request being handled, so a browser action and the API calls it causes
   * share one trace.
   */
  requestId?: string;
  fetch?: typeof globalThis.fetch;
}

/**
 * Guarantees every write carries an Idempotency-Key.
 *
 * This is a backstop, not the primary mechanism: a key generated here is unique
 * per call, so it makes the request *valid* but cannot make a retry replay. Code
 * that retries must pass its own key — see `idempotent()` — and this middleware
 * deliberately leaves a caller-supplied key alone.
 *
 * Exported so it can be tested directly: the generated types (correctly) will
 * not let a caller omit the header, so there is no way to exercise the
 * injection path through the client's public surface.
 */
export const idempotencyMiddleware: Middleware = {
  onRequest({ request }) {
    if (!WRITE_METHODS.has(request.method.toUpperCase())) {
      return undefined;
    }

    if (!request.headers.get(IDEMPOTENCY_KEY_HEADER)) {
      request.headers.set(IDEMPOTENCY_KEY_HEADER, ulid());
    }

    return request;
  },
};

/**
 * Supplies the Idempotency-Key a write operation's types require.
 *
 * Pass no argument for a one-shot write. Pass a key you generated earlier to
 * retry a write: reusing the key is what makes the server replay the original
 * response instead of acting twice.
 *
 *   const key = ulid();
 *   await client.POST("/thing", { ...idempotent(key), body });
 *   // ...on failure, retry with the SAME key:
 *   await client.POST("/thing", { ...idempotent(key), body });
 */
export function idempotent(key: string = ulid()) {
  return { params: { header: { [IDEMPOTENCY_KEY_HEADER]: key } } } as const;
}

function correlationMiddleware(requestId: string): Middleware {
  return {
    onRequest({ request }) {
      if (!request.headers.get(REQUEST_ID_HEADER)) {
        request.headers.set(REQUEST_ID_HEADER, requestId);
      }

      return request;
    },
  };
}

export function createApiClient(options: ApiClientOptions = {}) {
  const client = createClient<paths>({
    baseUrl:
      options.baseUrl ?? process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1",
    ...(options.fetch ? { fetch: options.fetch } : {}),
  });

  client.use(idempotencyMiddleware);

  if (options.requestId) {
    client.use(correlationMiddleware(options.requestId));
  }

  return client;
}

/**
 * Narrows an error body to a Problem.
 *
 * Code that branches on `problem.code` should be forced to check the shape
 * first — a network-level failure produces an error that is not a problem
 * document at all.
 */
export function isProblem(value: unknown): value is Problem {
  return (
    typeof value === "object" &&
    value !== null &&
    typeof (value as Problem).code === "string" &&
    typeof (value as Problem).status === "number"
  );
}

export type ApiClient = ReturnType<typeof createApiClient>;
