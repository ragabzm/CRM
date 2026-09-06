"use client";

import { request } from "@/lib/api/request";

/**
 * What the machine offers about one ticket.
 *
 * Four reads and no write. There is no accept, apply or send call here —
 * confirming a proposed category is the ordinary ticket PATCH an agent already
 * uses, carrying the version and their own name; sending a drafted reply is
 * the ordinary message POST from the composer they just edited.
 *
 * ABSENCE IS THE FAILURE MODE, and it arrives as a 200 with nothing in it: a
 * capability that is off, transmission disabled, or a provider that did not
 * answer all look the same from here. The panel renders nothing and the screen
 * around it is complete.
 */

export interface CategoryProposal {
  category_id: number;
  name: string;
}

export interface SuggestedArticle {
  id: string;
  title: string | null;
  type: string;
  internal_only: boolean;
  served_locale: string;
}

export async function assistSummary(
  ticketId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<string | null> {
  const body = await request<{ data: { summary: string | null } }>(
    `/tickets/${encodeURIComponent(ticketId)}/assist/summary`,
    { method: "GET", fetchImpl },
  );

  return body.data.summary;
}

export async function assistReply(
  ticketId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<string[]> {
  const body = await request<{ data: { suggestions: string[] } }>(
    `/tickets/${encodeURIComponent(ticketId)}/assist/reply`,
    { method: "GET", fetchImpl },
  );

  return body.data.suggestions;
}

export async function assistCategory(
  ticketId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<CategoryProposal | null> {
  const body = await request<{ data: { proposal: CategoryProposal | null } }>(
    `/tickets/${encodeURIComponent(ticketId)}/assist/category`,
    { method: "GET", fetchImpl },
  );

  return body.data.proposal;
}

export async function assistArticles(
  ticketId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<SuggestedArticle[]> {
  const body = await request<{ data: { articles: SuggestedArticle[] } }>(
    `/tickets/${encodeURIComponent(ticketId)}/assist/articles`,
    { method: "GET", fetchImpl },
  );

  return body.data.articles;
}
