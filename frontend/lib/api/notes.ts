"use client";

import { getCsrf, request } from "./request";

export interface CustomerNote {
  id: string;
  customer_id: string;
  author: { id: string | null; name: string };
  body: string;
  created_at: string | null;
  updated_at: string | null;
  /** True when the text is not what was originally written. */
  edited: boolean;
}

export async function listNotes(
  customerId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<CustomerNote[]> {
  const body = await request<{ data: CustomerNote[] }>(
    `/customers/${encodeURIComponent(customerId)}/notes`,
    { method: "GET", fetchImpl },
  );

  return body.data;
}

export async function addNote(
  customerId: string,
  text: string,
  fetchImpl: typeof fetch = fetch,
): Promise<CustomerNote> {
  await getCsrf(fetchImpl);

  return request(`/customers/${encodeURIComponent(customerId)}/notes`, {
    method: "POST",
    body: JSON.stringify({ body: text }),
    fetchImpl,
  });
}

export async function updateNote(
  noteId: string,
  text: string,
  fetchImpl: typeof fetch = fetch,
): Promise<CustomerNote> {
  await getCsrf(fetchImpl);

  return request(`/notes/${encodeURIComponent(noteId)}`, {
    method: "PATCH",
    body: JSON.stringify({ body: text }),
    fetchImpl,
  });
}

export async function deleteNote(noteId: string, fetchImpl: typeof fetch = fetch): Promise<void> {
  await getCsrf(fetchImpl);

  await request(`/notes/${encodeURIComponent(noteId)}`, { method: "DELETE", fetchImpl });
}
