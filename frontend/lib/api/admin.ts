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
  /** Always null for a secret — see `configured` for whether one is set. */
  value: unknown;
  default: unknown;
  secret: boolean;
  /**
   * Whether a secret has a value.
   *
   * The one fact about a credential a reader legitimately needs. Without it an
   * unset key and a set one look identical, and the only way to find out is to
   * break something.
   */
  configured: boolean;
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

export interface TestSendResult {
  status: string;
  provider: string;
  sent_to: string;
}

/**
 * Proves the channel works by actually sending something.
 *
 * Synchronous, unlike every other outbound email: the whole value is the
 * immediate answer. An administrator who has just changed a credential wants to
 * know now, not to go and read a log.
 */
export async function sendTestEmail(
  to?: string,
  fetchImpl: typeof fetch = fetch,
): Promise<TestSendResult> {
  await getCsrf(fetchImpl);

  return request("/admin/email/test", {
    method: "POST",
    body: JSON.stringify(to === undefined || to === "" ? {} : { to }),
    fetchImpl,
  });
}

export interface MailLogRow {
  id: string;
  direction: string;
  provider: string;
  address: string;
  subject: string | null;
  status: string;
  attempt: number;
  duration_ms: number | null;
  /** The provider's own words. A generic message gives an admin nothing to act on. */
  error: string | null;
  provider_code: string | null;
  ticket_id: string | null;
  message_id: string | null;
  occurred_at: string | null;
}

export async function listMailLog(
  options: { status?: string } = {},
  fetchImpl: typeof fetch = fetch,
): Promise<{ data: MailLogRow[]; meta: { total: number } }> {
  const query = options.status ? `?status=${encodeURIComponent(options.status)}` : "";

  return request(`/admin/email/log${query}`, { method: "GET", fetchImpl });
}

/**
 * One row of the exchange log — every outside system, not just mail.
 *
 * The mail log is a view of the same table filtered to one integration; this
 * is the whole of it. An administrator asking "did anything reach the outside
 * world last night?" should be able to ask once.
 */
export interface ExchangeLogRow {
  id: string;
  direction: string;
  integration: string;
  /** The endpoint or address reached. Never carries a credential. */
  target: string;
  /** `queued` | `succeeded` | `failed` | `abandoned`. */
  status: string;
  attempt: number;
  response_status: number | null;
  duration_ms: number | null;
  error: string | null;
  occurred_at: string;
}

export async function listExchangeLog(
  fetchImpl: typeof fetch = fetch,
): Promise<{ data: ExchangeLogRow[] }> {
  return request("/integrations/log", { method: "GET", fetchImpl });
}

export interface ErpTestResult {
  succeeded: boolean;
  /** Where it actually went, so a typo is visible rather than inferred. */
  endpoint: string;
  status: number | null;
  duration_ms: number | null;
  error: string | null;
}

/**
 * Runs a REAL exchange against the configured ERP.
 *
 * Not a ping. A reachable host with a rejected credential passes a ping and
 * fails at 3am on the first sync, which is exactly what the administrator
 * pressed this button to find out.
 */
export async function testErpExchange(fetchImpl: typeof fetch = fetch): Promise<ErpTestResult> {
  await getCsrf(fetchImpl);

  const response = await request<{ data: ErpTestResult }>("/integrations/erp/test", {
    method: "POST",
    body: JSON.stringify({}),
    fetchImpl,
  });

  return response.data;
}

export { ApiError } from "./request";

export interface QuarantinedMail {
  id: string;
  provider: string;
  from_address: string | null;
  subject: string | null;
  /** The parser's own words — the only thing that makes a failure diagnosable. */
  reason: string;
  received_at: string | null;
  resolved_at: string | null;
  /** Size only. The raw source is fetched one at a time, deliberately. */
  raw_bytes: number;
}

export interface QuarantinedMailDetail extends Omit<QuarantinedMail, "raw_bytes"> {
  /** A customer's entire email. Fetched by somebody diagnosing, never listed. */
  raw: string;
}

export async function listQuarantine(
  options: { outstandingOnly?: boolean } = {},
  fetchImpl: typeof fetch = fetch,
): Promise<{ data: QuarantinedMail[]; meta: { total: number } }> {
  const query = options.outstandingOnly ? "?resolved=false" : "";

  return request(`/admin/email/quarantine${query}`, { method: "GET", fetchImpl });
}

export function getQuarantinedMail(
  id: string,
  fetchImpl: typeof fetch = fetch,
): Promise<QuarantinedMailDetail> {
  return request(`/admin/email/quarantine/${encodeURIComponent(id)}`, {
    method: "GET",
    fetchImpl,
  });
}

/**
 * Feeds a quarantined message back through intake.
 *
 * The point of keeping the bytes: once the fault that stopped it is fixed, the
 * customer's email gets the second chance it would otherwise never have had.
 */
export async function replayQuarantinedMail(
  id: string,
  fetchImpl: typeof fetch = fetch,
): Promise<{ status: string; ticket_id?: string; reason?: string }> {
  await getCsrf(fetchImpl);

  return request(`/admin/email/quarantine/${encodeURIComponent(id)}/replay`, {
    method: "POST",
    fetchImpl,
  });
}

/* ------------------------------------------------------------------ *
 * Staff accounts and departments
 *
 * The endpoints for both shipped with the users-and-roles story and this
 * file had no function for either, so the Administration console said
 * "managed through the API today" and meant it literally: the only way to
 * add a colleague was a curl command.
 * ------------------------------------------------------------------ */

export interface StaffUser {
  id: number;
  name: string;
  email: string;
  /** Null for an account that somehow holds none. */
  role: string | null;
  department_id: number | null;
  is_active: boolean;
}

/**
 * An office, as a label.
 *
 * Read by every staff role and administered by none but an administrator —
 * an agent filters their queue by branch, and a label nobody can read is not
 * a label.
 *
 * There is no `deleteBranch`. A branch that closed still describes where
 * years of tickets happened; deactivation is the only way out.
 */
export interface Branch {
  id: number;
  name: string;
  code: string;
  is_active: boolean;
}

export async function listBranches(fetchImpl: typeof fetch = fetch): Promise<Branch[]> {
  const body = await request<{ data: Branch[] }>("/branches", { method: "GET", fetchImpl });

  return body.data;
}

export async function createBranch(
  input: { name: string; code: string },
  fetchImpl: typeof fetch = fetch,
): Promise<Branch> {
  const body = await request<{ data: Branch }>("/branches", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });

  return body.data;
}

export async function setBranchActive(
  id: number,
  isActive: boolean,
  fetchImpl: typeof fetch = fetch,
): Promise<Branch> {
  const body = await request<{ data: Branch }>(`/branches/${id}`, {
    method: "PATCH",
    body: JSON.stringify({ is_active: isActive }),
    fetchImpl,
  });

  return body.data;
}

export interface Department {
  id: number;
  name: string;
  is_active: boolean;
}

export async function listStaff(fetchImpl: typeof fetch = fetch): Promise<StaffUser[]> {
  const body = await request<{ data: StaffUser[] }>("/users", { method: "GET", fetchImpl });

  return body.data;
}

export async function createStaff(
  input: {
    name: string;
    email: string;
    role: string;
    department_id: number | null;
    is_active?: boolean;
  },
  fetchImpl: typeof fetch = fetch,
): Promise<StaffUser> {
  await getCsrf(fetchImpl);

  /*
   * No password in the payload. The API creates the account without a usable
   * one and the person sets their own through the reset flow — better than an
   * administrator inventing a password and then having to transmit it.
   */
  return request<StaffUser>("/users", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export async function updateStaff(
  id: number,
  input: Partial<{
    name: string;
    email: string;
    role: string;
    department_id: number | null;
    is_active: boolean;
  }>,
  fetchImpl: typeof fetch = fetch,
): Promise<StaffUser> {
  await getCsrf(fetchImpl);

  return request<StaffUser>(`/users/${id}`, {
    method: "PATCH",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export async function deactivateStaff(id: number, fetchImpl: typeof fetch = fetch): Promise<void> {
  await getCsrf(fetchImpl);

  /*
   * Deactivated, never deleted. A deleted account would orphan the
   * `assignee_id` and the author name on everything that person ever
   * touched — the history would stop saying who did the work.
   */
  return request<void>(`/users/${id}/deactivate`, { method: "POST", fetchImpl });
}

export async function listDepartments(fetchImpl: typeof fetch = fetch): Promise<Department[]> {
  const body = await request<{ data: Department[] }>("/departments", { method: "GET", fetchImpl });

  return body.data;
}

export async function createDepartment(
  input: { name: string },
  fetchImpl: typeof fetch = fetch,
): Promise<Department> {
  await getCsrf(fetchImpl);

  return request<Department>("/departments", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export async function updateDepartment(
  id: number,
  input: { name: string },
  fetchImpl: typeof fetch = fetch,
): Promise<Department> {
  await getCsrf(fetchImpl);

  return request<Department>(`/departments/${id}`, {
    method: "PATCH",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export async function deactivateDepartment(
  id: number,
  fetchImpl: typeof fetch = fetch,
): Promise<void> {
  await getCsrf(fetchImpl);

  return request<void>(`/departments/${id}/deactivate`, { method: "POST", fetchImpl });
}
