"use client";

import { request } from "@/lib/api/request";

/**
 * An agent's own tasks, reminders and mentions.
 *
 * `/me/…` everywhere: there is no shape of request here that asks for somebody
 * else's list, because these are the notes people write to themselves.
 *
 * A separate module from `tickets.ts` on purpose — these are not tickets, they
 * are personal work that happens to point at one, and folding them in would
 * make "what can this screen do to a ticket?" harder to answer.
 */

export interface PersonalTask {
  id: string;
  title: string;
  due_at: string | null;
  /** A timestamp, not a boolean: "when did you finish it" is the next question. */
  completed_at: string | null;
  /**
   * Decided by the SERVER. Overdue computed in the browser is decided against
   * the reader's own clock, which is the one place it can silently be wrong.
   */
  overdue: boolean;
  ticket_id: string | null;
  ticket_reference: string | null;
  ticket_subject: string | null;
}

export interface PersonalMention {
  id: string;
  message_id: string;
  ticket_id: string;
  reference: string;
  subject: string;
  author_name: string;
  /** A quoted extract. Home is a queue; a whole note would push the rest off it. */
  excerpt: string;
  read_at: string | null;
  mentioned_at: string;
}

export async function listTasks(fetchImpl: typeof fetch = fetch): Promise<PersonalTask[]> {
  const body = await request<{ data: PersonalTask[] }>("/me/tasks", { method: "GET", fetchImpl });

  return body.data;
}

export async function createTask(
  input: { title: string; ticket_id?: string; due_at?: string },
  fetchImpl: typeof fetch = fetch,
): Promise<PersonalTask> {
  const body = await request<{ data: PersonalTask }>("/me/tasks", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });

  return body.data;
}

export async function setTaskCompletion(
  id: string,
  completed: boolean,
  fetchImpl: typeof fetch = fetch,
): Promise<PersonalTask> {
  const body = await request<{ data: PersonalTask }>(`/me/tasks/${encodeURIComponent(id)}`, {
    method: "PATCH",
    body: JSON.stringify({ completed }),
    fetchImpl,
  });

  return body.data;
}

/** A reminder is set on a ticket or on a task — one of the two, never both. */
export function createReminder(
  input: { remind_at: string } & ({ ticket_id: string } | { task_id: string }),
  fetchImpl: typeof fetch = fetch,
): Promise<unknown> {
  return request("/me/reminders", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export async function listMentions(fetchImpl: typeof fetch = fetch): Promise<PersonalMention[]> {
  const body = await request<{ data: PersonalMention[] }>("/me/mentions", {
    method: "GET",
    fetchImpl,
  });

  return body.data;
}

export function markMentionRead(id: string, fetchImpl: typeof fetch = fetch): Promise<unknown> {
  return request(`/me/mentions/${encodeURIComponent(id)}/read`, { method: "POST", fetchImpl });
}
