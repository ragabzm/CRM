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

/** What a customer is shown about their own request. Deliberately short. */
export interface PortalRequestSummary {
  id: string;
  reference: string;
  subject: string;
  status: "open" | "pending" | "resolved" | "closed";
  created_at: string | null;
  updated_at: string | null;
}

export interface PortalMessage {
  id: string;
  /** `you` or `support` — never an agent's name. */
  from: "you" | "support";
  body: string;
  sent_at: string | null;
  attachments: Array<{ id: string; filename: string; byte_size: number }>;
}

export interface PortalRequestDetail extends PortalRequestSummary {
  description: string;
  messages: PortalMessage[];
}

export async function listPortalRequests(
  fetchImpl: typeof fetch = fetch,
): Promise<PortalRequestSummary[]> {
  const body = await request<{ data: PortalRequestSummary[] }>("/portal/requests", {
    method: "GET",
    fetchImpl,
  });

  return body.data;
}

export function getPortalRequest(
  id: string,
  fetchImpl: typeof fetch = fetch,
): Promise<PortalRequestDetail> {
  return request<PortalRequestDetail>(`/portal/requests/${encodeURIComponent(id)}`, {
    method: "GET",
    fetchImpl,
  });
}

export async function submitPortalRequest(
  input: {
    subject: string;
    description: string;
    category_id?: number | null;
    attachment_ids?: string[];
  },
  fetchImpl: typeof fetch = fetch,
): Promise<PortalRequestSummary> {
  await getCsrf(fetchImpl);

  return request<PortalRequestSummary>("/portal/requests", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export async function replyToPortalRequest(
  id: string,
  body: string,
  attachmentIds: string[] = [],
  fetchImpl: typeof fetch = fetch,
): Promise<PortalRequestDetail> {
  await getCsrf(fetchImpl);

  return request<PortalRequestDetail>(`/portal/requests/${encodeURIComponent(id)}/replies`, {
    method: "POST",
    body: JSON.stringify({ body, attachment_ids: attachmentIds }),
    fetchImpl,
  });
}

/**
 * Reopens a closed request.
 *
 * Past the configured window the API refuses with a 409 that carries a
 * `new_request_url` — the way forward, so a refusal is not a dead end.
 */
export async function reopenPortalRequest(
  id: string,
  fetchImpl: typeof fetch = fetch,
): Promise<PortalRequestDetail> {
  await getCsrf(fetchImpl);

  return request<PortalRequestDetail>(`/portal/requests/${encodeURIComponent(id)}/reopen`, {
    method: "POST",
    fetchImpl,
  });
}
