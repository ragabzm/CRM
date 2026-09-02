"use client";

import { getCsrf, request } from "./request";

export type TicketStatus = "open" | "pending" | "resolved" | "closed" | "reopened";
export type TicketPriority = "low" | "normal" | "high" | "urgent";
export type TicketChannel = "agent" | "portal" | "email" | "system";

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

export type MessageDirection = "inbound" | "outbound";

export interface TicketMessage {
  id: string;
  ticket_id: string;
  direction: MessageDirection;
  author: { type: string; id: string | null; name: string };
  body: string;
  sent_at: string | null;
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
  fetchImpl: typeof fetch = fetch,
): Promise<TicketMessage> {
  await getCsrf(fetchImpl);

  return request(`/tickets/${encodeURIComponent(ticketId)}/messages`, {
    method: "POST",
    body: JSON.stringify({ body, direction }),
    fetchImpl,
  });
}
