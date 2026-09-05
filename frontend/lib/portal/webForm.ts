"use client";

import { apiOrigin, request } from "@/lib/api/request";

/**
 * The public form's calls.
 *
 * No session, no CSRF priming and no bearer token: the person filling this in
 * has no account and nothing to authenticate with. `request` still sends
 * `credentials: "include"` and an `Idempotency-Key`, both of which are harmless
 * here — the API exempts these routes from the key and there is no cookie to
 * send.
 */

export interface WebFormCategory {
  id: number;
  name: string;
}

export interface WebFormSession {
  session_token: string;
  expires_at: string;
  /**
   * The categories the form offers.
   *
   * They arrive here rather than from `/ticket-categories` because that
   * endpoint is gated on `ticket.read`, which a stranger filling in a public
   * form does not have.
   */
  categories: WebFormCategory[];
}

export interface WebFormSubmission {
  name: string;
  contact: string;
  subject: string;
  category_id: number;
  message: string;
  attachment_ids?: string[];
  session_token?: string;
  /** The honeypot. Always sent, always empty when a person filled the form in. */
  hp_company: string;
  /** When the page was drawn, so the server can refuse an impossible fill time. */
  rendered_at: string;
}

export interface WebFormResult {
  status: string;
  reference: string | null;
}

export interface WebFormAttachment {
  id: string;
  filename: string;
  byte_size: number;
  mime_type: string;
  scan_status: string;
  downloadable: boolean;
}

/** A short-lived token anonymous uploads hang off, taken when the page loads. */
export async function startWebFormSession(
  fetchImpl: typeof fetch = fetch,
): Promise<WebFormSession> {
  return request<WebFormSession>("/inbound/web-form/session", { fetchImpl });
}

export async function submitWebForm(
  input: WebFormSubmission,
  fetchImpl: typeof fetch = fetch,
): Promise<WebFormResult> {
  return request<WebFormResult>("/inbound/web-form", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

/**
 * One file, against the draft token.
 *
 * `fetch` directly rather than `request`, because this is multipart: setting
 * `Content-Type: application/json` on a FormData body produces a request the
 * server cannot parse, and the boundary has to be the one the browser chose.
 */
export async function uploadWebFormAttachment(
  file: File,
  sessionToken: string,
  fetchImpl: typeof fetch = fetch,
): Promise<WebFormAttachment> {
  const body = new FormData();
  body.append("file", file);
  body.append("session_token", sessionToken);

  const response = await fetchImpl(`${apiOrigin()}/api/v1/inbound/web-form/attachments`, {
    method: "POST",
    credentials: "include",
    headers: { Accept: "application/json" },
    body,
  });

  if (!response.ok) {
    const problem: unknown = await response.json().catch(() => null);
    const detail =
      typeof problem === "object" && problem !== null && "detail" in problem
        ? String((problem as { detail: unknown }).detail)
        : "The file could not be uploaded.";

    throw new Error(detail);
  }

  return (await response.json()) as WebFormAttachment;
}
