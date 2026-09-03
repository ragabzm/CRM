"use client";

import { getCsrf, request } from "./request";

export type TicketStatus = "open" | "pending" | "resolved" | "closed" | "reopened";
export type TicketPriority = "low" | "normal" | "high" | "urgent";
export type TicketChannel = "agent" | "portal" | "email" | "system";

/** Where a timer stands. `paused` and `met` are not "on track". */
export type SlaStateValue = "on_track" | "at_risk" | "breached" | "met" | "paused";

export interface SlaTimer {
  state: SlaStateValue;
  elapsed_minutes: number;
  target_minutes: number;
  /** Negative once breached — by how much is what a supervisor asks about. */
  remaining_minutes: number;
  due_at: string | null;
}

export interface SlaBlock {
  /** The worse of the two timers: a list has room for one badge. */
  state: SlaStateValue;
  response: SlaTimer;
  resolution: SlaTimer;
}

export interface Ticket {
  id: string;
  reference: string;
  subject: string;
  description: string;
  customer_id: string;
  channel: TicketChannel;
  status: TicketStatus;
  priority: TicketPriority;
  category_id: number | null;
  assignee_id: number | null;
  department_id: number | null;
  creator_type: string;
  creator_id: string | null;
  /** Submit this back on any change to the five contended fields. */
  version: number;
  created_at: string | null;
  updated_at: string | null;
  /**
   * Null means the engine is not tracking, which is NOT the same as fine.
   * A deployment with SLA switched off knows nothing about its targets.
   */
  sla: SlaBlock | null;
}

export interface CreateTicketInput {
  subject: string;
  description: string;
  customer_id: string;
  channel: TicketChannel;
  category_id?: number | null;
  priority?: TicketPriority;
  department_id?: number | null;
}

export function getTicket(id: string, fetchImpl: typeof fetch = fetch): Promise<Ticket> {
  return request(`/tickets/${encodeURIComponent(id)}`, { method: "GET", fetchImpl });
}

export async function createTicket(
  input: CreateTicketInput,
  /**
   * Reuse a key to RETRY: the server replays the original response rather than
   * opening a second ticket for one problem. The New Ticket form captures one
   * on mount so a double-clicked Create is safe.
   */
  idempotencyKey?: string,
  fetchImpl: typeof fetch = fetch,
): Promise<Ticket> {
  await getCsrf(fetchImpl);

  return request("/tickets", {
    method: "POST",
    body: JSON.stringify(input),
    ...(idempotencyKey ? { idempotencyKey } : {}),
    fetchImpl,
  });
}

/** Any subset of the five contended attributes. `version` is required. */
export async function updateTicketAttributes(
  id: string,
  version: number,
  changes: Partial<
    Pick<Ticket, "status" | "priority" | "category_id" | "assignee_id" | "department_id">
  >,
  fetchImpl: typeof fetch = fetch,
): Promise<Ticket> {
  await getCsrf(fetchImpl);

  return request(`/tickets/${encodeURIComponent(id)}`, {
    method: "PATCH",
    body: JSON.stringify({ version, ...changes }),
    fetchImpl,
  });
}

export async function assignTicket(
  id: string,
  version: number,
  assigneeId: number | null,
  fetchImpl: typeof fetch = fetch,
): Promise<Ticket> {
  await getCsrf(fetchImpl);

  return request(`/tickets/${encodeURIComponent(id)}/assign`, {
    method: "POST",
    body: JSON.stringify({ version, assignee_id: assigneeId }),
    fetchImpl,
  });
}

export async function resolveTicket(
  id: string,
  version: number,
  resolutionNote: string,
  fetchImpl: typeof fetch = fetch,
): Promise<Ticket> {
  await getCsrf(fetchImpl);

  return request(`/tickets/${encodeURIComponent(id)}/resolve`, {
    method: "POST",
    body: JSON.stringify({ version, resolution_note: resolutionNote }),
    fetchImpl,
  });
}

export async function reopenTicket(
  id: string,
  version: number,
  reason?: string,
  fetchImpl: typeof fetch = fetch,
): Promise<Ticket> {
  await getCsrf(fetchImpl);

  return request(`/tickets/${encodeURIComponent(id)}/reopen`, {
    method: "POST",
    body: JSON.stringify({ version, ...(reason ? { reason } : {}) }),
    fetchImpl,
  });
}

/* --- the thread --- */

/**
 * Which way a message went, or that it went nowhere.
 *
 * `internal` is a note colleagues leave for each other. It is not a direction
 * in any honest sense — nothing travels — but it lives in this one field so
 * that "may the customer see this?" has one answer in one place.
 */
export type MessageDirection = "inbound" | "outbound" | "internal";

/** Only outbound messages have one; the others never made a journey. */
export type DeliveryState = "queued" | "sent" | "failed";

export interface MessageAttachment {
  id: string;
  filename: string;
  byte_size: number;
  /** Server-sniffed. The client's claim is never stored. */
  mime_type: string;
  scan_status: string;
}

export interface TicketMessage {
  id: string;
  ticket_id: string;
  direction: MessageDirection;
  author: { type: string; id: string | null; name: string };
  body: string;
  sent_at: string | null;
  delivery_state: DeliveryState | null;
  attachments: MessageAttachment[];
}

export async function listTicketMessages(
  ticketId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<TicketMessage[]> {
  const body = await request<{ data: TicketMessage[] }>(
    `/tickets/${encodeURIComponent(ticketId)}/messages`,
    { method: "GET", fetchImpl },
  );

  return body.data;
}

/**
 * Appends a reply.
 *
 * Takes NO version, deliberately. Two colleagues writing different replies have
 * not conflicted — they have both said something, and both belong in the
 * thread. See the backend's AppendMessage.
 */
export async function appendTicketMessage(
  ticketId: string,
  body: string,
  direction: MessageDirection = "outbound",
  attachmentIds: string[] = [],
  fetchImpl: typeof fetch = fetch,
): Promise<TicketMessage> {
  await getCsrf(fetchImpl);

  return request(`/tickets/${encodeURIComponent(ticketId)}/messages`, {
    method: "POST",
    body: JSON.stringify({ body, direction, attachment_ids: attachmentIds }),
    fetchImpl,
  });
}

/** Who did the thing an event records. */
export type TicketEventActor =
  | { type: "system"; reason: string | null }
  | { type: "staff" | "portal"; id: string | null; display_name: string | null };

export interface TicketEvent {
  id: string;
  /** `ticket.status_changed`, `ticket.resolved`, … */
  kind: string;
  actor: TicketEventActor;
  /** Changed fields only, never the whole ticket. */
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  /** Detail specific to the kind — a resolution note, a reason. */
  meta: Record<string, unknown> | null;
  version_after: number;
  occurred_at: string;
}

export interface TicketEventPage {
  data: TicketEvent[];
  next_cursor: string | null;
  has_more: boolean;
}

/**
 * One page of a ticket's history, oldest first.
 *
 * The panel that renders this is Story 4.4; this is the loader it will call.
 * Cursor-paginated rather than offset: a history that is being appended to
 * while someone reads it would otherwise repeat or skip rows between pages.
 */
export async function listTicketEvents(
  ticketId: string,
  options: { cursor?: string | null; limit?: number } = {},
  fetchImpl: typeof fetch = fetch,
): Promise<TicketEventPage> {
  const query = new URLSearchParams();

  if (options.cursor) query.set("cursor", options.cursor);
  if (options.limit !== undefined) query.set("limit", String(options.limit));

  const suffix = query.size > 0 ? `?${query.toString()}` : "";

  return request<TicketEventPage>(`/tickets/${encodeURIComponent(ticketId)}/events${suffix}`, {
    method: "GET",
    fetchImpl,
  });
}

/**
 * Puts a failed send back in the queue.
 *
 * Retry, not "send again": the message already exists and already says who
 * wrote it and when. A second one would put the agent's words in the thread
 * twice for a failure that was never theirs.
 */
export async function retryTicketMessage(
  ticketId: string,
  messageId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<TicketMessage> {
  await getCsrf(fetchImpl);

  return request(
    `/tickets/${encodeURIComponent(ticketId)}/messages/${encodeURIComponent(messageId)}/retry`,
    { method: "POST", fetchImpl },
  );
}

export interface CustomerContext {
  customer_id: string;
  reference: string;
  full_name: string;
  state: string;
  department: { id: number; name: string | null } | null;
  open_ticket_count: number;
  recent_ticket_count: number;
  /** So "4 recent" can be read as "4 in the last 30 days". */
  recent_window_days: number;
  last_interaction_at: string | null;
}

/** Who this ticket is for, and what else they have open. One query, server-side. */
export function getCustomerContext(
  ticketId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<CustomerContext> {
  return request<CustomerContext>(`/tickets/${encodeURIComponent(ticketId)}/customer-context`, {
    method: "GET",
    fetchImpl,
  });
}

export interface QuickReply {
  id: number | string;
  title: string;
  body: string;
}

/**
 * The shared reply snippets an agent can drop into the composer.
 *
 * Read from the agent-facing route, not the admin one: using a quick reply is
 * doing your job, while editing the list is administration.
 */
export async function listQuickReplies(fetchImpl: typeof fetch = fetch): Promise<QuickReply[]> {
  const body = await request<{ data: QuickReply[] }>("/quick-replies", {
    method: "GET",
    fetchImpl,
  });

  return body.data;
}

/**
 * Changes a ticket's properties, under the version guard.
 *
 * The version travels as `If-Match`, echoing the ETag the read handed back. On
 * a mismatch the server refuses with 409 rather than silently overwriting
 * whatever the other person did.
 */
export async function updateTicketProperties(
  ticketId: string,
  version: number,
  changes: Partial<{
    status: string;
    priority: string;
    category_id: number | null;
    assignee_id: number | null;
    department_id: number | null;
  }>,
  fetchImpl: typeof fetch = fetch,
): Promise<Ticket> {
  await getCsrf(fetchImpl);

  return request<Ticket>(`/tickets/${encodeURIComponent(ticketId)}`, {
    method: "PATCH",
    headers: { "If-Match": `W/"${version}"` },
    body: JSON.stringify(changes),
    fetchImpl,
  });
}

export interface TicketListParams {
  status?: string[];
  priority?: string[];
  category_id?: number[];
  /** Accepts the `unassigned` sentinel alongside user ids. */
  assignee_id?: Array<number | "unassigned">;
  department_id?: number[];
  created_from?: string;
  created_to?: string;
  q?: string;
  sort?: string;
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
}

export interface TicketListPage {
  data: Ticket[];
  meta: { total: number; per_page: number; current_page: number; last_page: number };
}

export interface TicketCounts {
  assigned_to_me: number;
  unassigned: number;
  /** Null until the SLA module exists — "not known", not "none". */
  at_risk: number | null;
  breached: number | null;
  pending_customer_reply: number;
}

/**
 * Turns list params into the query string the API and the address bar share.
 *
 * Comma-separated rather than repeated keys, so a link in the counts strip is
 * something an agent can read and edit in the address bar — and so the URL a
 * count links to is literally the filter it stands for.
 */
export function ticketListQuery(params: TicketListParams): string {
  const query = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === "") continue;

    if (Array.isArray(value)) {
      if (value.length === 0) continue;

      query.set(key, value.join(","));
    } else {
      query.set(key, String(value));
    }
  }

  return query.toString();
}

export function listTickets(
  params: TicketListParams = {},
  fetchImpl: typeof fetch = fetch,
): Promise<TicketListPage> {
  const query = ticketListQuery(params);

  return request<TicketListPage>(`/tickets${query === "" ? "" : `?${query}`}`, {
    method: "GET",
    fetchImpl,
  });
}

/** The five numbers on the agent's home screen. One aggregate query, server-side. */
export function ticketCounts(fetchImpl: typeof fetch = fetch): Promise<TicketCounts> {
  return request<TicketCounts>("/tickets/counts", { method: "GET", fetchImpl });
}
