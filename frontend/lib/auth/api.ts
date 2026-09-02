"use client";

import { createApiClient } from "@/lib/api/client";
import { ApiError, getCsrf, request, type RequestInitWithFetch } from "@/lib/api/request";

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

/**
 * Auth calls fail with an AuthError, which is the shared ApiError under a name
 * the sign-in screens already use.
 */
export { ApiError as AuthError, getCsrf };

/** Session routes are exempt from the Idempotency-Key requirement. */
function call<T>(path: string, init: RequestInitWithFetch = {}): Promise<T> {
  return request<T>(path, init);
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
