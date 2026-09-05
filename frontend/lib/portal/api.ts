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

  /**
   * What they said about how it went, if they said anything.
   *
   * `null` is the THIRD state and means nobody answered — never neutral,
   * never zero. The screen renders an invitation for null and the answer they
   * gave otherwise.
   */
  satisfaction: boolean | null;
  satisfaction_comment: string | null;

  /**
   * Whether they can still answer or change their answer.
   *
   * Computed on the SERVER, because the change window is a server rule. A
   * screen that worked it out itself would offer a control the API then
   * refuses — which is the same as lying to somebody about what will happen
   * when they tap.
   */
  can_rate: boolean;
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

/**
 * Says whether it went well. One tap, and the comment is optional.
 *
 * `positive` is a boolean and there is no third value on the wire — no stars,
 * no scale, nothing to average or convert.
 */
export function ratePortalRequest(
  id: string,
  positive: boolean,
  comment?: string,
  fetchImpl: typeof fetch = fetch,
): Promise<PortalRequestDetail> {
  return request<PortalRequestDetail>(`/portal/requests/${encodeURIComponent(id)}/rating`, {
    method: "POST",
    body: JSON.stringify({ positive, ...(comment === undefined ? {} : { comment }) }),
    fetchImpl,
  });
}

/**
 * The same answer, sent from an emailed invitation instead of a session.
 *
 * The signature IS the authorisation — there is no cookie here and the person
 * may have no account at all. It travelled from the email to the address bar
 * to here, and is passed back untouched: `expires` before `signature`, in the
 * order the server signed them, because the server rebuilds the string it
 * hashed from exactly these two.
 */
export async function rateByInvitation(
  input: { ticket: string; verdict: "up" | "down"; expires: string; signature: string },
  comment?: string,
  fetchImpl: typeof fetch = fetch,
): Promise<{ satisfaction: boolean; satisfaction_comment: string | null }> {
  /*
   * CSRF first, and it is NOT optional here.
   *
   * The API routes are Sanctum-stateful, so a same-site POST is checked
   * against the XSRF cookie — and somebody arriving from an email has never
   * had one. Without this the comment box returned 419 while the rating
   * itself, which the GET link had already recorded, looked fine: the
   * customer wrote a sentence, pressed save, and was told nothing worked.
   *
   * The signature is still what authorises the write. This only proves the
   * request came from the page we served.
   */
  await getCsrf(fetchImpl);

  const query = `expires=${encodeURIComponent(input.expires)}&signature=${encodeURIComponent(input.signature)}`;

  return request<{ satisfaction: boolean; satisfaction_comment: string | null }>(
    `/feedback/${encodeURIComponent(input.ticket)}/${input.verdict}?${query}`,
    {
      method: "POST",
      body: JSON.stringify(comment === undefined ? {} : { comment }),
      fetchImpl,
    },
  );
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
