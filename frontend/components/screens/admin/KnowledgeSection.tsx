"use client";

import { useLocale, useTranslations } from "next-intl";
import { useEffect, useState } from "react";

import { ArticleLifecycleControls } from "@/components/domain/ArticleLifecycleControls/ArticleLifecycleControls";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { RowSkeleton } from "@/components/domain/RowSkeleton/RowSkeleton";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import { ActionBar } from "@/components/domain/ActionBar/ActionBar";
import { ApiError } from "@/lib/api/errors";
import {
  archiveArticle,
  createArticle,
  deleteArticle,
  getArticle,
  listArticleCategories,
  listArticles,
  publishArticle,
  type Article,
  type ArticleCategory,
  type ArticleDetail,
  type ArticleStatus,
  type ArticleType,
} from "@/lib/api/knowledge";

import { ArticleEditor } from "./knowledge/ArticleEditor";

const TYPES: ArticleType[] = ["faq", "help", "solution", "guide"];
const STATUSES: ArticleStatus[] = ["draft", "published", "archived"];

/**
 * The knowledge base: what we have written down, and whether it is ready.
 *
 * The list carries two chips per row and they mean different things —
 * lifecycle says whether an answer is READY, visibility says who it is FOR.
 * Showing them as one status would make "an internal published article" and
 * "a public draft" indistinguishable, and both are ordinary.
 */
export function KnowledgeSection() {
  const t = useTranslations("admin.knowledge");
  const locale = useLocale();

  const [articles, setArticles] = useState<Article[] | null>(null);
  const [categories, setCategories] = useState<ArticleCategory[]>([]);
  const [open, setOpen] = useState<ArticleDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const [type, setType] = useState<ArticleType | "">("");
  const [status, setStatus] = useState<ArticleStatus | "">("");
  const [audience, setAudience] = useState<"" | "internal" | "public">("");
  const [q, setQ] = useState("");

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      try {
        const [page, cats] = await Promise.all([
          listArticles({
            ...(type === "" ? {} : { type }),
            ...(status === "" ? {} : { status }),
            ...(audience === "" ? {} : { internal_only: audience === "internal" }),
            ...(q.trim() === "" ? {} : { q: q.trim() }),
          }),
          listArticleCategories(),
        ]);

        if (cancelled) return;
        setArticles(page.data);
        setCategories(cats);
        setError(null);
      } catch {
        if (cancelled) return;
        // Out loud. An empty list and a failed request look identical, and one
        // of them means "nothing is written yet".
        setArticles([]);
        setError(t("loadFailed"));
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [type, status, audience, q, t]);

  async function act(action: () => Promise<Article>) {
    setBusy(true);
    setError(null);

    try {
      const updated = await action();
      setArticles((current) =>
        (current ?? []).map((row) => (row.id === updated.id ? { ...row, ...updated } : row)),
      );
      if (open !== null && open.id === updated.id) {
        setOpen({ ...open, ...updated });
      }
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? (caught.problem?.detail ?? t("actionFailed"))
          : t("actionFailed"),
      );
    } finally {
      setBusy(false);
    }
  }

  async function remove(id: string) {
    setBusy(true);
    setError(null);

    try {
      await deleteArticle(id);
      setArticles((current) => (current ?? []).filter((row) => row.id !== id));
      setOpen(null);
    } catch (caught) {
      /*
       * The refusal names the alternative. A published article can only be
       * archived, and the server says so in words the reader can act on.
       */
      setError(
        caught instanceof ApiError
          ? (caught.problem?.detail ?? t("actionFailed"))
          : t("actionFailed"),
      );
    } finally {
      setBusy(false);
    }
  }

  async function draft() {
    if (categories[0] === undefined) return;

    setBusy(true);

    try {
      const created = await createArticle({
        type: "faq",
        category_id: categories[0].id,
        default_locale: "en",
      });

      setArticles((current) => [created, ...(current ?? [])]);
      setOpen(await getArticle(created.id));
    } catch {
      setError(t("actionFailed"));
    } finally {
      setBusy(false);
    }
  }

  /*
   * The reader's own language, not English.
   *
   * A category list rendered in English inside an Arabic page is the small
   * inconsistency that makes the whole screen feel half-translated — and it
   * was hard-coded here, which is exactly how it survives review.
   */
  const categoryName = (id: number) => {
    const category = categories.find((c) => c.id === id);

    if (category === undefined) return "—";

    return locale.startsWith("ar") ? category.name.ar : category.name.en;
  };

  if (open !== null) {
    return (
      <section className="flex flex-col gap-4" data-slot="admin-knowledge-article">
        <ActionBar
          actions={[{ id: "back", label: t("backToList"), onSelect: () => setOpen(null) }]}
        />

        {error !== null && <FormAlert tone="error">{error}</FormAlert>}

        <ArticleLifecycleControls
          article={open}
          busy={busy}
          onPublish={() => void act(() => publishArticle(open.id))}
          onArchive={() => void act(() => archiveArticle(open.id))}
          onDelete={() => void remove(open.id)}
        />

        <ArticleEditor article={open} onSaved={setOpen} />
      </section>
    );
  }

  return (
    <section className="flex flex-col gap-4" data-slot="admin-knowledge">
      <header className="flex flex-col gap-1">
        <h2 className="text-lg font-semibold text-fg-default">{t("title")}</h2>
        <p className="text-sm text-fg-muted">{t("hint")}</p>
      </header>

      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      <div className="flex flex-wrap items-center gap-3">
        <SegmentedFilter
          label={t("filterStatus")}
          value={status}
          options={[
            { value: "", label: t("all") },
            ...STATUSES.map((s) => ({ value: s, label: t(`status.${s}`) })),
          ]}
          onChange={(value) => setStatus(value as ArticleStatus | "")}
        />

        <SegmentedFilter
          label={t("filterAudience")}
          value={audience}
          options={[
            { value: "", label: t("all") },
            { value: "internal", label: t("visibility.internal") },
            { value: "public", label: t("visibility.public") },
          ]}
          onChange={(value) => setAudience(value as "" | "internal" | "public")}
        />

        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-fg-default">{t("filterType")}</span>
          <select
            value={type}
            onChange={(event) => setType(event.target.value as ArticleType | "")}
            className="h-11 rounded-md border border-border-default bg-surface-default px-2 text-sm"
          >
            <option value="">{t("all")}</option>
            {TYPES.map((value) => (
              <option key={value} value={value}>
                {t(`type.${value}`)}
              </option>
            ))}
          </select>
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-fg-default">{t("search")}</span>
          <input
            type="search"
            value={q}
            onChange={(event) => setQ(event.target.value)}
            className="h-11 rounded-md border border-border-default bg-surface-default px-3 text-sm"
          />
        </label>

        <ActionBar
          actions={[
            {
              id: "new",
              label: t("newArticle"),
              primary: true,
              disabled: busy || categories.length === 0,
              onSelect: () => void draft(),
            },
          ]}
        />
      </div>

      {categories.length === 0 && articles !== null && (
        // A category is required to file an article, so this is a dead end
        // until an administrator makes one. Said plainly rather than leaving a
        // disabled button nobody can explain.
        <FormAlert tone="error">{t("needsACategory")}</FormAlert>
      )}

      {articles === null && <RowSkeleton rows={4} label={t("loading")} />}

      {articles !== null && articles.length === 0 && error === null && (
        <EmptyState headline={t("empty")} />
      )}

      {articles !== null && articles.length > 0 && (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-fg-muted">
                <th scope="col" className="p-2 text-start font-medium">
                  {t("columnTitle")}
                </th>
                <th scope="col" className="p-2 text-start font-medium">
                  {t("columnType")}
                </th>
                <th scope="col" className="p-2 text-start font-medium">
                  {t("columnCategory")}
                </th>
                <th scope="col" className="p-2 text-start font-medium">
                  {t("columnLanguages")}
                </th>
                <th scope="col" className="p-2 text-start font-medium">
                  {t("columnStatus")}
                </th>
                <th scope="col" className="p-2 text-start font-medium">
                  {t("columnAudience")}
                </th>
              </tr>
            </thead>
            <tbody>
              {articles.map((article) => (
                <tr key={article.id} className="border-t border-border-subtle">
                  <td className="p-2">
                    <button
                      type="button"
                      className="text-start font-medium text-fg-default underline-offset-2 hover:underline"
                      onClick={() => void getArticle(article.id).then(setOpen)}
                    >
                      {/*
                        The title, falling back to a marker rather than to the
                        identifier: "Untitled" tells an author their article
                        has no words yet, and a ULID tells them nothing.
                      */}
                      {article.title ?? t("untitled")}
                    </button>
                  </td>
                  <td className="p-2 text-fg-muted">{t(`type.${article.type}`)}</td>
                  <td className="p-2 text-fg-muted">{categoryName(article.category_id)}</td>
                  <td className="p-2 text-fg-muted" data-slot="languages">
                    {/*
                      Which languages exist, so an Arabic-only article reads as
                      complete rather than as one somebody forgot to finish.
                    */}
                    {article.available_locales.length === 0
                      ? t("noLanguages")
                      : article.available_locales.map((l) => t(`locale.${l}`)).join(" · ")}
                  </td>
                  <td className="p-2">
                    <span data-slot="status-chip" data-status={article.status}>
                      {t(`status.${article.status}`)}
                    </span>
                  </td>
                  <td className="p-2">
                    <span
                      data-slot="audience-chip"
                      data-audience={article.internal_only ? "internal" : "public"}
                    >
                      {t(article.internal_only ? "visibility.internal" : "visibility.public")}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
