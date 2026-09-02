"use client";

import { isProblem, type Problem } from "./client";
import { ulid } from "./ulid";

/**
 * The one browser→API transport.
 *
 * Cookie-mode Sanctum throughout: no token is requested, returned or stored,
 * and nothing touches localStorage or sessionStorage. The session lives in an
 * http-only cookie the browser attaches by itself and JavaScript cannot read.
 *
 * This lives in one module rather than one per feature because the plumbing —
 * the XSRF echo, `credentials: "include"`, the Idempotency-Key on writes — is
 * exactly the kind of thing that gets copied once, then updated in only one of
 * the copies.
 */

/** Base origin of the API, without the /api/v1 suffix. */
export function apiOrigin(): string {
  // The full expression, not a destructured read: Next inlines these at build
  // time by literal match.
  const base = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1";

  return base.replace(/\/api\/v1\/?$/, "");
}

/**
 * Primes the CSRF cookie.
 *
 * Sanctum sets XSRF-TOKEN here; the browser echoes it back on later requests.
 * It is a double-submit cookie, not a credential — knowing it grants nothing
 * without the session cookie, which JavaScript cannot read.
 */
export async function getCsrf(fetchImpl: typeof fetch = fetch): Promise<void> {
  await fetchImpl(`${apiOrigin()}/sanctum/csrf-cookie`, {
    method: "GET",
    credentials: "include",
  });
}

/** Reads the XSRF cookie so it can be echoed in the header Laravel expects. */
export function xsrfToken(): string | null {
  if (typeof document === "undefined") return null;

  const match = /(?:^|;\s*)XSRF-TOKEN=([^;]*)/.exec(document.cookie);

  return match ? decodeURIComponent(match[1]!) : null;
}

/** An API response that was not a success, carrying its problem document. */
export class ApiError extends Error {
  constructor(
    message: string,
    readonly problem: Problem | null,
    readonly status: number,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

const WRITE_METHODS = new Set(["POST", "PUT", "PATCH", "DELETE"]);

export interface RequestInitWithFetch extends RequestInit {
  fetchImpl?: typeof fetch;
  /**
   * Reuse a key to RETRY a write: the server replays the original response
   * instead of acting twice. Omit it for a one-shot write and a fresh key is
   * minted, which makes the request valid but is not a retry.
   */
  idempotencyKey?: string;
}

/** One JSON call against the API, with the cookie plumbing every request needs. */
export async function request<T>(path: string, init: RequestInitWithFetch = {}): Promise<T> {
  const { fetchImpl = fetch, idempotencyKey, ...rest } = init;
  const token = xsrfToken();
  const method = (rest.method ?? "GET").toUpperCase();

  const response = await fetchImpl(`${apiOrigin()}/api/v1${path}`, {
    ...rest,
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { "X-XSRF-TOKEN": token } : {}),
      // Every write, without the caller having to remember. The server rejects
      // a write that arrives without one.
      ...(WRITE_METHODS.has(method) ? { "Idempotency-Key": idempotencyKey ?? ulid() } : {}),
      ...(rest.headers ?? {}),
    },
  });

  if (response.status === 204) {
    return undefined as T;
  }

  const body = await response.json().catch(() => null);

  if (!response.ok) {
    const problem = isProblem(body) ? body : null;

    throw new ApiError(
      problem?.detail ?? problem?.title ?? "Request failed",
      problem,
      response.status,
    );
  }

  return body as T;
}
