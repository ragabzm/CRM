"use client";

import { request } from "@/lib/api/request";

/**
 * The agent's side of live chat.
 *
 * Deliberately three calls: who is waiting, take one, close one. Replying is
 * NOT here — an agent answers a chat through the ticket's own message
 * endpoint, because the conversation is a ticket and its transcript is
 * ordinary messages. A second send path would be a second place for the
 * delivery state, the attachment rules and the internal-note guard to be got
 * wrong.
 *
 * Nothing in this module talks to the widget's endpoints. Those take a
 * conversation token and no session, and are called only by the widget itself.
 */

export interface ChatConversationRow {
  id: string;
  /** `waiting` · `taken` · `ended` · `abandoned` — derived, never stored. */
  state: string;
  visitor_name: string | null;
  ticket_id: string | null;
  reference: string | null;
  subject: string | null;
  taken_by: number | null;
  waiting_since: string | null;
  last_activity_at: string | null;
}

export interface ChatDeskView {
  /**
   * The SHORT interval, from the server.
   *
   * Read rather than hard-coded, because it is the same number the widget
   * polls on and the two disagreeing would mean an agent seeing a message
   * seconds after the visitor believed it had been read.
   */
  poll_seconds: number;
  waiting: ChatConversationRow[];
  mine: ChatConversationRow[];
}

export async function chatDesk(fetchImpl: typeof fetch = fetch): Promise<ChatDeskView> {
  const body = await request<{ data: ChatDeskView }>("/chat-desk/conversations", {
    method: "GET",
    fetchImpl,
  });

  return body.data;
}

export async function takeChat(
  id: string,
  fetchImpl: typeof fetch = fetch,
): Promise<ChatConversationRow> {
  const body = await request<{ data: ChatConversationRow }>(
    `/chat-desk/conversations/${encodeURIComponent(id)}/take`,
    { method: "POST", fetchImpl },
  );

  return body.data;
}

export async function closeChat(
  id: string,
  fetchImpl: typeof fetch = fetch,
): Promise<ChatConversationRow> {
  const body = await request<{ data: ChatConversationRow }>(
    `/chat-desk/conversations/${encodeURIComponent(id)}/close`,
    { method: "POST", fetchImpl },
  );

  return body.data;
}
