"use client";

import { getCsrf, request } from "./request";

/**
 * The customer record's calls.
 *
 * Reading and writing are separate capabilities server-side; nothing here
 * decides that. The UI hides what it cannot use, the server refuses what it
 * must not allow, and the two are independent on purpose.
 */

export type CustomerState = "active" | "inactive";
export type ContactKind = "email" | "phone";

export interface CustomerIdentifier {
  id?: string;
  kind: ContactKind;
  /** As the customer gave it. The normalised form is never shown. */
  value: string;
  is_primary?: boolean;
}

export interface Customer {
  id: string;
  reference: string;
  full_name: string;
  department: { id: number; name: string | null };
  state: CustomerState;
  preferred_channel: ContactKind | null;
  identifiers: CustomerIdentifier[];
  notes?: string | null;
  created_at?: string | null;
  updated_at: string | null;
  deactivated_at: string | null;
}

export interface CustomerPage {
  data: Customer[];
  meta: { page: number; per_page: number; total: number; last_page: number };
}

export interface CustomerFilters {
  q?: string;
  state?: CustomerState | "all";
  department_id?: number;
  limit?: number;
  page?: number;
}

export interface DuplicateMatch {
  customer_id: string;
  reference: string;
  full_name: string;
  state: CustomerState;
  matched_value: string;
  matched_kind: ContactKind;
}

export interface CustomerInput {
  full_name: string;
  department_id: number;
  preferred_channel?: ContactKind | null;
  notes?: string | null;
  identifiers: Array<{ kind: ContactKind; value: string; is_primary?: boolean }>;
  confirm_create_duplicate?: boolean;
}

function queryString(filters: Record<string, unknown>): string {
  const query = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    // Empty strings omitted rather than sent: `?q=` is a value the server has
    // to consider, not an absent filter.
    if (value !== undefined && value !== null && String(value) !== "") {
      query.set(key, String(value));
    }
  }

  const suffix = query.toString();

  return suffix ? `?${suffix}` : "";
}

export function listCustomers(
  filters: CustomerFilters = {},
  fetchImpl: typeof fetch = fetch,
): Promise<CustomerPage> {
  return request(`/customers${queryString({ ...filters })}`, { method: "GET", fetchImpl });
}

export function getCustomer(id: string, fetchImpl: typeof fetch = fetch): Promise<Customer> {
  // Resolves whatever the state: a link in an old ticket must still open the
  // person it refers to.
  return request(`/customers/${encodeURIComponent(id)}`, { method: "GET", fetchImpl });
}

export async function previewDuplicates(
  input: { emails?: string[]; phones?: string[]; exclude_customer_id?: string },
  fetchImpl: typeof fetch = fetch,
): Promise<DuplicateMatch[]> {
  await getCsrf(fetchImpl);

  const body = await request<{ matches: DuplicateMatch[] }>("/customers/duplicates/preview", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });

  return body.matches;
}

export async function createCustomer(
  input: CustomerInput,
  fetchImpl: typeof fetch = fetch,
): Promise<Customer> {
  await getCsrf(fetchImpl);

  return request("/customers", { method: "POST", body: JSON.stringify(input), fetchImpl });
}

export async function updateCustomer(
  id: string,
  input: Partial<CustomerInput>,
  fetchImpl: typeof fetch = fetch,
): Promise<Customer> {
  await getCsrf(fetchImpl);

  return request(`/customers/${encodeURIComponent(id)}`, {
    method: "PATCH",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export async function setCustomerState(
  id: string,
  state: CustomerState,
  fetchImpl: typeof fetch = fetch,
): Promise<Customer> {
  await getCsrf(fetchImpl);

  // Deactivate, never delete: the record is the anchor for every ticket and
  // note attached to it.
  const action = state === "inactive" ? "deactivate" : "reactivate";

  return request(`/customers/${encodeURIComponent(id)}/${action}`, { method: "POST", fetchImpl });
}

/* --- interaction timeline --- */

export type TimelineKind = "ticket_opened" | "message_inbound" | "message_outbound";

export interface TimelineEntry {
  id: string;
  kind: TimelineKind;
  ticket_id: string;
  /** The human reference, so a row is quotable without a second lookup. */
  ticket_ref: string;
  occurred_at: string;
  /** Already truncated server-side. Null for a ticket opening. */
  preview: string | null;
}

export interface TimelinePage {
  data: TimelineEntry[];
  /** Opaque. Pass it back verbatim; never build one by hand. */
  next_cursor: string | null;
  has_more: boolean;
}

export function listCustomerTimeline(
  customerId: string,
  options: { cursor?: string | null; limit?: number } = {},
  fetchImpl: typeof fetch = fetch,
): Promise<TimelinePage> {
  const query = new URLSearchParams();

  if (options.cursor) query.set("cursor", options.cursor);
  if (options.limit) query.set("limit", String(options.limit));

  const suffix = query.toString();

  return request(
    `/customers/${encodeURIComponent(customerId)}/timeline${suffix ? `?${suffix}` : ""}`,
    { method: "GET", fetchImpl },
  );
}
