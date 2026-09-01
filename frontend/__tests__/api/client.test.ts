import { describe, expect, it, vi } from "vitest";

import {
  IDEMPOTENCY_KEY_HEADER,
  REQUEST_ID_HEADER,
  createApiClient,
  idempotencyMiddleware,
  idempotent,
  isProblem,
} from "@/lib/api/client";
import { ULID_PATTERN, ulid } from "@/lib/api/ulid";

/**
 * Records the Request the client actually issued, and replies with the given
 * body so openapi-fetch has something well-formed to parse.
 */
function recordingFetch(body: unknown = { status: "ok" }, init: ResponseInit = { status: 200 }) {
  const calls: Request[] = [];

  const fetchImpl = vi.fn(async (input: Request | string | URL, requestInit?: RequestInit) => {
    calls.push(input instanceof Request ? input : new Request(input, requestInit));

    return new Response(JSON.stringify(body), {
      ...init,
      headers: { "Content-Type": "application/json", ...(init.headers ?? {}) },
    });
  });

  return { calls, fetchImpl: fetchImpl as unknown as typeof globalThis.fetch };
}

/**
 * Drives the middleware the way openapi-fetch does. The hook may return a
 * Request, a short-circuit Response, or nothing; this one only ever mutates and
 * returns the Request, and the assertion here pins that down.
 */
async function throughMiddleware(request: Request): Promise<Request> {
  const onRequest = idempotencyMiddleware.onRequest;
  if (!onRequest) throw new Error("The idempotency middleware has no onRequest hook.");

  const result = await onRequest({ request } as unknown as Parameters<typeof onRequest>[0]);

  if (result instanceof Response) {
    throw new Error("The idempotency middleware must not short-circuit the request.");
  }

  return result ?? request;
}

describe("Idempotency-Key injection", () => {
  it.each(["POST", "PUT", "PATCH", "DELETE"])("attaches a ULID to %s", async (method) => {
    const request = await throughMiddleware(new Request("http://api.test/thing", { method }));

    expect(request.headers.get(IDEMPOTENCY_KEY_HEADER)).toMatch(ULID_PATTERN);
  });

  it.each(["GET", "HEAD"])("attaches nothing to %s", async (method) => {
    const request = await throughMiddleware(new Request("http://api.test/thing", { method }));

    expect(request.headers.get(IDEMPOTENCY_KEY_HEADER)).toBeNull();
  });

  it("gives each write its own key, so two writes never replay each other", async () => {
    const first = await throughMiddleware(new Request("http://api.test/thing", { method: "POST" }));
    const second = await throughMiddleware(new Request("http://api.test/thing", { method: "POST" }));

    expect(first.headers.get(IDEMPOTENCY_KEY_HEADER)).not.toBe(
      second.headers.get(IDEMPOTENCY_KEY_HEADER),
    );
  });

  it("leaves a caller-supplied key alone, which is how a retry is expressed", async () => {
    const key = ulid();
    const request = await throughMiddleware(
      new Request("http://api.test/thing", {
        method: "POST",
        headers: { [IDEMPOTENCY_KEY_HEADER]: key },
      }),
    );

    expect(request.headers.get(IDEMPOTENCY_KEY_HEADER)).toBe(key);
  });
});

describe("idempotent()", () => {
  it("supplies the header the generated types require", async () => {
    const { calls, fetchImpl } = recordingFetch({ status: "ok", echo: {} });
    const client = createApiClient({ fetch: fetchImpl, baseUrl: "http://api.test/api/v1" });
    const key = ulid();

    await client.POST("/healthz-echo", { ...idempotent(key), body: undefined });

    expect(calls[0]!.headers.get(IDEMPOTENCY_KEY_HEADER)).toBe(key);
  });

  it("generates a key when none is given", () => {
    expect(idempotent().params.header[IDEMPOTENCY_KEY_HEADER]).toMatch(ULID_PATTERN);
  });

  it("reuses one key across a retry, which is what makes the server replay", async () => {
    const { calls, fetchImpl } = recordingFetch({ status: "ok", echo: {} });
    const client = createApiClient({ fetch: fetchImpl, baseUrl: "http://api.test/api/v1" });
    const retry = idempotent();

    await client.POST("/healthz-echo", { ...retry, body: undefined });
    await client.POST("/healthz-echo", { ...retry, body: undefined });

    expect(calls[0]!.headers.get(IDEMPOTENCY_KEY_HEADER)).toBe(
      calls[1]!.headers.get(IDEMPOTENCY_KEY_HEADER),
    );
  });
});

describe("X-Request-Id", () => {
  it("is propagated when the caller carries one across", async () => {
    const { calls, fetchImpl } = recordingFetch();
    const client = createApiClient({
      fetch: fetchImpl,
      baseUrl: "http://api.test/api/v1",
      requestId: "01HZY000000000000000000000",
    });

    await client.GET("/healthz");

    expect(calls[0]!.headers.get(REQUEST_ID_HEADER)).toBe("01HZY000000000000000000000");
  });

  it("is absent when there is nothing to propagate", async () => {
    const { calls, fetchImpl } = recordingFetch();
    const client = createApiClient({ fetch: fetchImpl, baseUrl: "http://api.test/api/v1" });

    await client.GET("/healthz");

    expect(calls[0]!.headers.get(REQUEST_ID_HEADER)).toBeNull();
  });
});

describe("problem responses", () => {
  it("surfaces the RFC 9457 body as a typed error", async () => {
    const problem = {
      type: "https://errors.ragab-crm/platform.not_found",
      title: "Resource not found.",
      status: 404,
      detail: "No resource matches the requested URI.",
      instance: "/api/v1/healthz",
      code: "platform.not_found",
      trace_id: "01HZY000000000000000000000",
    };

    const { fetchImpl } = recordingFetch(problem, {
      status: 404,
      headers: { "Content-Type": "application/problem+json" },
    });

    const client = createApiClient({ fetch: fetchImpl, baseUrl: "http://api.test/api/v1" });
    const { data, error } = await client.GET("/healthz");

    expect(data).toBeUndefined();
    expect(isProblem(error)).toBe(true);
    if (isProblem(error)) {
      expect(error.code).toBe("platform.not_found");
      expect(error.status).toBe(404);
    }
  });

  it("rejects values that are not problem documents", () => {
    expect(isProblem(null)).toBe(false);
    expect(isProblem("nope")).toBe(false);
    expect(isProblem({ code: "platform.not_found" })).toBe(false);
  });
});

describe("ulid", () => {
  it("matches the format the API accepts", () => {
    for (let i = 0; i < 200; i++) {
      expect(ulid()).toMatch(ULID_PATTERN);
    }
  });

  it("sorts lexicographically by creation time", () => {
    expect(ulid(1_700_000_000_000) < ulid(1_700_000_001_000)).toBe(true);
  });

  it("is 26 characters", () => {
    expect(ulid()).toHaveLength(26);
  });
});
