"use client";

import { SESSION_EXPIRED_CODES, SESSION_EXPIRED_EVENT, isProblem } from "./client";
import { ApiError, TicketRefusedError, TicketStaleVersionError } from "./errors";
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

/*
 * Re-exported so the many modules that import ApiError from here keep working.
 * It is DECLARED in errors.ts because TicketStaleVersionError extends it, and
 * a class cannot extend something defined in a module that imports it back.
 */
export { ApiError, TicketRefusedError, TicketStaleVersionError } from "./errors";

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

/**
 * Tells the app a session has ended, so SessionExpiryListener can send the
 * reader back to sign-in.
 *
 * This is the PUBLISHER. It was missing: the listener has always been mounted
 * in the root layout and has always had tests, but those tests dispatched the
 * event themselves, so nothing ever noticed that no production code path fired
 * it. A lapsed session therefore left the reader on a rendered page whose every
 * request was quietly failing.
 *
 * Fired here rather than per screen for the usual reason: any request can be
 * the one that discovers the session is gone, and a per-screen check is a check
 * the next screen forgets.
 */
function announceIfSessionEnded(status: number, problem: { code?: string } | null): void {
  if (status !== 401) return;

  // A 401 whose code we do not recognise still means unauthenticated. Treating
  // an unknown code as "not really expired" would strand the reader, which is
  // the exact failure this function exists to prevent.
  if (problem?.code !== undefined && !SESSION_EXPIRED_CODES.includes(problem.code)) return;

  if (typeof window === "undefined") return;

  window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
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

    announceIfSessionEnded(response.status, problem);

    /*
     * The one place a stale ticket version is recognised.
     *
     * Here rather than in each screen: every ticket write can lose this race,
     * and a per-screen check is a check the next screen forgets. Callers catch
     * TicketStaleVersionError and render the shared banner.
     */
    const stale = TicketStaleVersionError.from(body, response.status);

    if (stale) throw stale;

    // The other ticket refusals, recognised in the same one place.
    const refused = TicketRefusedError.from(body, response.status);

    if (refused) throw refused;

    throw new ApiError(
      problem?.detail ?? problem?.title ?? "Request failed",
      problem,
      response.status,
    );
  }

  return body as T;
}
