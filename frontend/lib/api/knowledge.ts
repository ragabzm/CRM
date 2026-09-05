"use client";

import { request } from "@/lib/api/request";

/** What kind of answer an article is. A label — it changes nothing else. */
export type ArticleType = "faq" | "help" | "solution" | "guide";

/** Draft → Published → Archived. There is no fourth, and no review state. */
export type ArticleStatus = "draft" | "published" | "archived";

export type ArticleLocale = "en" | "ar";

export interface Article {
  id: string;
  type: ArticleType;
  category_id: number;
  /** Who it is FOR. Independent of `status`, which is whether it is READY. */
  internal_only: boolean;
  status: ArticleStatus;
  /** The language a reader is served when their own is missing. */
  default_locale: ArticleLocale;
  /** Which languages exist. An article with only `ar` is complete. */
  available_locales: ArticleLocale[];
  /**
   * The title in whichever language the reader would be served.
   *
   * Null only while an article has no words at all. On the list as well as the
   * record — a list of identifiers is a list nobody can find anything in.
   */
  title: string | null;
  /** Both conditions together, computed by the server so nobody recomputes it. */
  is_customer_visible: boolean;
  /**
   * False once the article has ever been published — permanently.
   *
   * The interface offers Delete or Archive on this, and the server refuses on
   * the same rule. One answer, computed once.
   */
  can_delete: boolean;
  has_been_published: boolean;
  published_at: string | null;
  published_by: string | null;
  archived_at: string | null;
  archived_by: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ArticleTranslation {
  locale: ArticleLocale;
  title: string;
  body: string;
}

/** One article read in full: the served language, its body, and every version. */
export interface ArticleDetail extends Article {
  served_locale: ArticleLocale;
  body: string | null;
  translations: ArticleTranslation[];
}

export interface ArticleCategory {
  id: number;
  name: { en: string; ar: string };
  sort_order: number;
  article_count: number;
}

export interface ArticleFilters {
  type?: ArticleType;
  status?: ArticleStatus;
  category_id?: number;
  internal_only?: boolean;
  q?: string;
}

export async function listArticles(
  filters: ArticleFilters = {},
  fetchImpl: typeof fetch = fetch,
): Promise<{ data: Article[]; meta: { total: number } }> {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== "") params.set(key, String(value));
  }

  const query = params.toString();

  return request(`/admin/knowledge/articles${query === "" ? "" : `?${query}`}`, { fetchImpl });
}

export function getArticle(id: string, fetchImpl: typeof fetch = fetch): Promise<ArticleDetail> {
  return request(`/admin/knowledge/articles/${encodeURIComponent(id)}`, { fetchImpl });
}

export function createArticle(
  input: {
    type: ArticleType;
    category_id: number;
    default_locale: ArticleLocale;
    internal_only?: boolean;
  },
  fetchImpl: typeof fetch = fetch,
): Promise<Article> {
  return request("/admin/knowledge/articles", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export function updateArticle(
  id: string,
  changes: Partial<{
    type: ArticleType;
    category_id: number;
    default_locale: ArticleLocale;
    internal_only: boolean;
  }>,
  fetchImpl: typeof fetch = fetch,
): Promise<Article> {
  return request(`/admin/knowledge/articles/${encodeURIComponent(id)}`, {
    method: "PATCH",
    body: JSON.stringify(changes),
    fetchImpl,
  });
}

export function deleteArticle(
  id: string,
  fetchImpl: typeof fetch = fetch,
): Promise<{ deleted: string }> {
  return request(`/admin/knowledge/articles/${encodeURIComponent(id)}`, {
    method: "DELETE",
    fetchImpl,
  });
}

export function publishArticle(id: string, fetchImpl: typeof fetch = fetch): Promise<Article> {
  return request(`/admin/knowledge/articles/${encodeURIComponent(id)}/publish`, {
    method: "POST",
    fetchImpl,
  });
}

export function archiveArticle(id: string, fetchImpl: typeof fetch = fetch): Promise<Article> {
  return request(`/admin/knowledge/articles/${encodeURIComponent(id)}/archive`, {
    method: "POST",
    fetchImpl,
  });
}

export function saveArticleTranslation(
  id: string,
  locale: ArticleLocale,
  input: { title: string; body: string },
  fetchImpl: typeof fetch = fetch,
): Promise<ArticleDetail> {
  return request(`/admin/knowledge/articles/${encodeURIComponent(id)}/translations/${locale}`, {
    method: "PUT",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

/** One search result: enough to render a row, never the body. */
export interface ArticleSearchHit {
  id: string;
  title: string | null;
  type: ArticleType;
  category_id: number;
  status: ArticleStatus;
  internal_only: boolean;
  served_locale: ArticleLocale;
  available_locales: ArticleLocale[];
}

/**
 * Staff search. Internal articles included — that is what they are for.
 *
 * Called from inside a ticket on every debounced keystroke, so it asks for the
 * least it can: the body is only wanted when somebody opens a result.
 */
export async function searchArticles(
  term: string,
  limit = 10,
  fetchImpl: typeof fetch = fetch,
): Promise<ArticleSearchHit[]> {
  const body = await request<{ data: ArticleSearchHit[] }>(
    `/knowledge/search?q=${encodeURIComponent(term)}&limit=${limit}`,
    { fetchImpl },
  );

  return body.data;
}

/**
 * A stable link to an article, keyed on its ID and never on a slug.
 *
 * A retitled article's link in a two-year-old reply still resolves, because
 * nothing in the URL depends on what the article is called today.
 */
export function articleUrl(id: string): string {
  return `/help/articles/${id}`;
}

export async function listArticleCategories(
  fetchImpl: typeof fetch = fetch,
): Promise<ArticleCategory[]> {
  const body = await request<{ data: ArticleCategory[] }>("/admin/knowledge/categories", {
    fetchImpl,
  });

  return body.data;
}
