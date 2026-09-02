"use client";

import { apiOrigin, getCsrf, xsrfToken } from "./request";
import { isProblem, type Problem } from "./client";
import { ApiError } from "./request";
import { ulid } from "./ulid";

export type ScanStatus = "pending" | "clean" | "failed";
export type AttachmentOwnerType = "customer" | "ticket" | "message";

export interface Attachment {
  id: string;
  owner_type: AttachmentOwnerType;
  owner_id: string;
  filename: string;
  byte_size: number;
  mime_type: string;
  uploaded_at: string | null;
  scan_status: ScanStatus;
  /** Why a scan failed. Null unless it did. */
  scan_reason: string | null;
  /** Derived server-side from the scan status, so the two cannot disagree. */
  downloadable: boolean;
}

export async function listAttachments(
  ownerType: AttachmentOwnerType,
  ownerId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<Attachment[]> {
  const query = new URLSearchParams({ owner_type: ownerType, owner_id: ownerId });

  const response = await fetchImpl(`${apiOrigin()}/api/v1/attachments?${query}`, {
    method: "GET",
    credentials: "include",
    headers: { Accept: "application/json" },
  });

  const body = await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(
      "Attachments could not be loaded",
      isProblem(body) ? body : null,
      response.status,
    );
  }

  return (body as { data: Attachment[] }).data;
}

/**
 * Uploads one file.
 *
 * Sent as multipart with NO Content-Type header of our own: the browser has to
 * set it, because only the browser knows the multipart boundary it generated.
 * Setting it by hand produces a body the server cannot parse.
 */
export async function uploadAttachment(
  input: { file: File; ownerType: AttachmentOwnerType; ownerId: string },
  fetchImpl: typeof fetch = fetch,
): Promise<Attachment> {
  await getCsrf(fetchImpl);

  const form = new FormData();
  form.append("owner_type", input.ownerType);
  form.append("owner_id", input.ownerId);
  form.append("file", input.file);

  const token = xsrfToken();

  const response = await fetchImpl(`${apiOrigin()}/api/v1/attachments`, {
    method: "POST",
    credentials: "include",
    headers: {
      Accept: "application/json",
      ...(token ? { "X-XSRF-TOKEN": token } : {}),
      "Idempotency-Key": ulid(),
    },
    body: form,
  });

  const body: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(
      (isProblem(body) ? (body as Problem).detail : null) ?? "Upload failed",
      isProblem(body) ? (body as Problem) : null,
      response.status,
    );
  }

  return body as Attachment;
}

/**
 * Where the browser should go to download a file.
 *
 * A plain URL rather than a fetch: the endpoint answers 302 to a short-lived
 * signed link, and letting the browser follow it keeps the bytes out of
 * JavaScript entirely.
 */
export function downloadUrl(attachmentId: string): string {
  return `${apiOrigin()}/api/v1/attachments/${encodeURIComponent(attachmentId)}/download`;
}
