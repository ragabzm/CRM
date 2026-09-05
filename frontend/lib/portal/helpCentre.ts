"use client";

import { request } from "@/lib/api/request";

/** A row in the help centre list. Never a body — that needs its own request. */
export interface HelpArticleSummary {
  id: string;
  title: string | null;
  category_id: number;
  served_locale: "en" | "ar";
  available_locales: Array<"en" | "ar">;
}

export interface HelpCategory {
  id: number;
  name: string;
}

export interface HelpArticle extends HelpArticleSummary {
  /** Sanitised HTML, cleaned on write. Rendered, never executed. */
  body: string | null;
  updated_at: string | null;
}

/**
 * The customer help centre.
 *
 * No session and no account: requiring one to read an answer is a help centre
 * that only helps people who already got in.
 */
export async function searchHelpCentre(
  params: { q?: string; category_id?: number } = {},
  fetchImpl: typeof fetch = fetch,
): Promise<{ data: HelpArticleSummary[]; categories: HelpCategory[] }> {
  const query = new URLSearchParams();

  if (params.q !== undefined && params.q.trim() !== "") query.set("q", params.q.trim());
  if (params.category_id !== undefined) query.set("category_id", String(params.category_id));

  const suffix = query.toString();

  return request(`/help/articles${suffix === "" ? "" : `?${suffix}`}`, { fetchImpl });
}

export function getHelpArticle(id: string, fetchImpl: typeof fetch = fetch): Promise<HelpArticle> {
  return request<HelpArticle>(`/help/articles/${encodeURIComponent(id)}`, { fetchImpl });
}
