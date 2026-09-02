"use client";

import { getCsrf, request } from "./request";

/**
 * The configuration console's calls.
 *
 * Every one of these is gated server-side on the `setting.manage` capability.
 * Nothing here decides whether the user is allowed to do something — the UI
 * hides what it cannot use, the server refuses what it must not allow, and the
 * two are independent on purpose.
 */

/** How a setting is rendered and what values it will accept. */
export type SettingType = "bool" | "int" | "string" | "json" | "duration_seconds" | "enum";

export interface Setting {
  key: string;
  type: SettingType;
  /** Redacted to a dot-string for secrets, and null when a secret is unset. */
  value: unknown;
  default: unknown;
  secret: boolean;
  summary: string;
  allowed_values: string[] | null;
}

export interface Bilingual {
  en: string;
  ar: string;
}

export interface QuickReply {
  id: string;
  label: Bilingual;
  body: Bilingual;
}

export interface Category {
  id: number;
  name: Bilingual;
  sort_order: number;
}

export interface Priority {
  value: string;
}

/* --- settings --- */

export async function listSettings(fetchImpl: typeof fetch = fetch): Promise<Setting[]> {
  const body = await request<{ data: Setting[] }>("/admin/settings", { method: "GET", fetchImpl });

  return body.data;
}

export async function updateSetting(
  key: string,
  value: unknown,
  fetchImpl: typeof fetch = fetch,
): Promise<{ key: string; value: unknown }> {
  await getCsrf(fetchImpl);

  return request("/admin/settings/" + encodeURIComponent(key), {
    method: "PATCH",
    body: JSON.stringify({ value }),
    fetchImpl,
  });
}

/* --- quick replies --- */

export async function listQuickReplies(fetchImpl: typeof fetch = fetch): Promise<QuickReply[]> {
  const body = await request<{ data: QuickReply[] }>("/admin/quick-replies", {
    method: "GET",
    fetchImpl,
  });

  return body.data;
}

export async function createQuickReply(
  input: { label: Bilingual; body: Bilingual },
  fetchImpl: typeof fetch = fetch,
): Promise<QuickReply> {
  await getCsrf(fetchImpl);

  // No id in the payload. The server mints it; one chosen here could be picked
  // to collide with an existing reply and overwrite it.
  return request("/admin/quick-replies", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export async function updateQuickReply(
  id: string,
  input: { label: Bilingual; body: Bilingual },
  fetchImpl: typeof fetch = fetch,
): Promise<QuickReply> {
  await getCsrf(fetchImpl);

  return request("/admin/quick-replies/" + encodeURIComponent(id), {
    method: "PATCH",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export async function deleteQuickReply(
  id: string,
  fetchImpl: typeof fetch = fetch,
): Promise<QuickReply[]> {
  await getCsrf(fetchImpl);

  const body = await request<{ data: QuickReply[] }>(
    "/admin/quick-replies/" + encodeURIComponent(id),
    { method: "DELETE", fetchImpl },
  );

  return body.data;
}

export async function reorderQuickReplies(
  order: string[],
  fetchImpl: typeof fetch = fetch,
): Promise<QuickReply[]> {
  await getCsrf(fetchImpl);

  // The COMPLETE list of ids. A partial list is refused by the server rather
  // than silently deleting whatever was omitted.
  const body = await request<{ data: QuickReply[] }>("/admin/quick-replies/reorder", {
    method: "POST",
    body: JSON.stringify({ order }),
    fetchImpl,
  });

  return body.data;
}

/* --- categories --- */

export async function listCategories(fetchImpl: typeof fetch = fetch): Promise<Category[]> {
  const body = await request<{ data: Category[] }>("/admin/categories", {
    method: "GET",
    fetchImpl,
  });

  return body.data;
}

export async function createCategory(
  name: Bilingual,
  fetchImpl: typeof fetch = fetch,
): Promise<Category> {
  await getCsrf(fetchImpl);

  return request("/admin/categories", {
    method: "POST",
    body: JSON.stringify({ name }),
    fetchImpl,
  });
}

export async function updateCategory(
  id: number,
  name: Bilingual,
  fetchImpl: typeof fetch = fetch,
): Promise<Category> {
  await getCsrf(fetchImpl);

  return request(`/admin/categories/${id}`, {
    method: "PATCH",
    body: JSON.stringify({ name }),
    fetchImpl,
  });
}

export async function deleteCategory(id: number, fetchImpl: typeof fetch = fetch): Promise<void> {
  await getCsrf(fetchImpl);

  await request(`/admin/categories/${id}`, { method: "DELETE", fetchImpl });
}

/* --- priorities --- */

export async function listPriorities(
  fetchImpl: typeof fetch = fetch,
): Promise<{ data: Priority[]; editable: boolean }> {
  return request("/admin/priorities", { method: "GET", fetchImpl });
}

/* --- audit log --- */

export interface AuditActor {
  id: string | null;
  type: "user" | "service" | "guest";
  /** Denormalised at write time, so it survives a rename or a deletion. */
  label: string;
}

export interface AuditEntrySummary {
  id: string;
  occurred_at: string | null;
  actor: AuditActor;
  action: string;
  target: { type: string | null; id: string | null };
  source_ip: string | null;
  request_id: string | null;
}

export interface AuditEntryDetail extends AuditEntrySummary {
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
}

export interface AuditPage {
  data: AuditEntrySummary[];
  meta: { page: number; per_page: number; total: number; last_page: number };
  /** The action vocabulary the server actually records, for the filter. */
  actions: string[];
}

export interface AuditFilters {
  actor_search?: string;
  actor_id?: string;
  action?: string;
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

export function listAuditEntries(
  filters: AuditFilters = {},
  fetchImpl: typeof fetch = fetch,
): Promise<AuditPage> {
  const query = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    // Empty strings omitted, not sent: `?action=` would be a filter value the
    // server has to reject rather than an absent filter.
    if (value !== undefined && value !== null && String(value) !== "") {
      query.set(key, String(value));
    }
  }

  const suffix = query.toString();

  return request(`/audit-entries${suffix ? `?${suffix}` : ""}`, { method: "GET", fetchImpl });
}

export function getAuditEntry(
  id: string,
  fetchImpl: typeof fetch = fetch,
): Promise<AuditEntryDetail> {
  return request(`/audit-entries/${encodeURIComponent(id)}`, { method: "GET", fetchImpl });
}

/* --- email --- */

export async function sendTestEmail(fetchImpl: typeof fetch = fetch): Promise<{ status: string }> {
  await getCsrf(fetchImpl);

  return request("/admin/email/test", { method: "POST", fetchImpl });
}

export { ApiError } from "./request";
