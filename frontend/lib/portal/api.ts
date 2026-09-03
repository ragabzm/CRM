"use client";

import { getCsrf, request } from "@/lib/api/request";

/**
 * The portal's own auth calls.
 *
 * Cookie-mode Sanctum on the `portal` guard: no token is requested, returned or
 * stored, and nothing touches web storage. The session lives in an http-only
 * cookie the browser attaches by itself.
 */

export interface PortalAccount {
  id: number;
  name: string;
  email: string;
  preferred_locale: "en" | "ar";
  customer_id: string | null;
}

export async function registerPortalAccount(
  input: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    preferred_locale?: "en" | "ar";
  },
  fetchImpl: typeof fetch = fetch,
): Promise<PortalAccount> {
  // CSRF first: Sanctum rejects the POST without it, and priming on every
  // attempt is cheaper than reasoning about whether the cookie is still fresh.
  await getCsrf(fetchImpl);

  return request<PortalAccount>("/portal/auth/register", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export async function portalSignIn(
  credentials: { email: string; password: string; remember?: boolean },
  fetchImpl: typeof fetch = fetch,
): Promise<PortalAccount> {
  await getCsrf(fetchImpl);

  return request<PortalAccount>("/portal/auth/login", {
    method: "POST",
    body: JSON.stringify(credentials),
    fetchImpl,
  });
}

export async function portalSignOut(fetchImpl: typeof fetch = fetch): Promise<void> {
  await request("/portal/auth/logout", { method: "POST", fetchImpl });
}

export function portalMe(fetchImpl: typeof fetch = fetch): Promise<PortalAccount> {
  return request<PortalAccount>("/portal/auth/me", { method: "GET", fetchImpl });
}

export async function portalForgotPassword(
  email: string,
  fetchImpl: typeof fetch = fetch,
): Promise<void> {
  await getCsrf(fetchImpl);

  await request("/portal/auth/password/forgot", {
    method: "POST",
    body: JSON.stringify({ email }),
    fetchImpl,
  });
}

export async function portalResetPassword(
  input: { token: string; email: string; password: string; password_confirmation: string },
  fetchImpl: typeof fetch = fetch,
): Promise<void> {
  await getCsrf(fetchImpl);

  await request("/portal/auth/password/reset", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}
