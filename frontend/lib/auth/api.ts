"use client";

import { createApiClient, type Problem, isProblem } from "@/lib/api/client";

/**
 * The staff authentication calls.
 *
 * Everything here is cookie-mode Sanctum: no token is requested, returned or
 * stored, and nothing touches localStorage or sessionStorage. The session lives
 * in an http-only cookie the browser attaches on its own and JavaScript cannot
 * read — which is what makes "no credential is ever handled by client
 * JavaScript" true by construction rather than by discipline.
 */

export interface StaffUser {
  id: number;
  name: string;
  email: string;
  preferred_locale: string;
  roles: string[];
}

export interface ProfileFields {
  name?: string;
  preferred_locale?: "en" | "ar";
}

/** Base origin of the API, without the /api/v1 suffix. */
function apiOrigin(): string {
  const base = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1";

  return base.replace(/\/api\/v1\/?$/, "");
}

/**
 * Primes the CSRF cookie.
 *
 * Sanctum sets XSRF-TOKEN here; the browser echoes it back on subsequent
 * requests. It is a double-submit cookie, not a credential — knowing it grants
 * nothing without the session cookie, which JavaScript cannot read.
 */
export async function getCsrf(fetchImpl: typeof fetch = fetch): Promise<void> {
  await fetchImpl(`${apiOrigin()}/sanctum/csrf-cookie`, {
    method: "GET",
    credentials: "include",
  });
}

/** Reads the XSRF cookie so it can be echoed in the header Laravel expects. */
function xsrfToken(): string | null {
  if (typeof document === "undefined") return null;

  const match = /(?:^|;\s*)XSRF-TOKEN=([^;]*)/.exec(document.cookie);

  return match ? decodeURIComponent(match[1]!) : null;
}

export class AuthError extends Error {
  constructor(
    message: string,
    readonly problem: Problem | null,
    readonly status: number,
  ) {
    super(message);
    this.name = "AuthError";
  }
}

/**
 * One JSON call against the API, with the cookie plumbing every auth request
 * needs.
 */
async function call<T>(
  path: string,
  init: RequestInit & { fetchImpl?: typeof fetch } = {},
): Promise<T> {
  const { fetchImpl = fetch, ...rest } = init;
  const token = xsrfToken();

  const response = await fetchImpl(`${apiOrigin()}/api/v1${path}`, {
    ...rest,
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { "X-XSRF-TOKEN": token } : {}),
      ...(rest.headers ?? {}),
    },
  });

  if (response.status === 204) {
    return undefined as T;
  }

  const body = await response.json().catch(() => null);

  if (!response.ok) {
    const problem = isProblem(body) ? body : null;

    throw new AuthError(problem?.detail ?? problem?.title ?? "Request failed", problem, response.status);
  }

  return body as T;
}

export async function login(
  credentials: { email: string; password: string; remember?: boolean },
  fetchImpl: typeof fetch = fetch,
): Promise<StaffUser> {
  // CSRF first: Sanctum rejects the POST without it, and priming on every
  // sign-in is cheaper than reasoning about whether the cookie is still fresh.
  await getCsrf(fetchImpl);

  return call<StaffUser>("/auth/login", {
    method: "POST",
    body: JSON.stringify(credentials),
    fetchImpl,
  });
}

export async function logout(fetchImpl: typeof fetch = fetch): Promise<void> {
  await call<{ status: string }>("/auth/logout", { method: "POST", fetchImpl });
}

export function me(fetchImpl: typeof fetch = fetch): Promise<StaffUser> {
  return call<StaffUser>("/auth/me", { method: "GET", fetchImpl });
}

export function sessionInfo(
  fetchImpl: typeof fetch = fetch,
): Promise<{ inactivity_minutes: number; authenticated: boolean }> {
  return call("/auth/session", { method: "GET", fetchImpl });
}

export async function forgotPassword(
  input: { email: string },
  fetchImpl: typeof fetch = fetch,
): Promise<void> {
  await getCsrf(fetchImpl);
  await call("/auth/password/forgot", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export async function resetPassword(
  input: { token: string; email: string; password: string; password_confirmation: string },
  fetchImpl: typeof fetch = fetch,
): Promise<void> {
  await getCsrf(fetchImpl);
  await call("/auth/password/reset", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export function getProfile(fetchImpl: typeof fetch = fetch): Promise<StaffUser> {
  return call<StaffUser>("/profile", { method: "GET", fetchImpl });
}

export async function updateProfile(
  fields: ProfileFields,
  fetchImpl: typeof fetch = fetch,
): Promise<StaffUser> {
  await getCsrf(fetchImpl);

  return call<StaffUser>("/profile", {
    method: "PATCH",
    body: JSON.stringify(fields),
    fetchImpl,
  });
}

export async function changePassword(
  input: { current_password: string; password: string; password_confirmation: string },
  fetchImpl: typeof fetch = fetch,
): Promise<void> {
  await getCsrf(fetchImpl);
  await call("/profile/password", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export { createApiClient };
