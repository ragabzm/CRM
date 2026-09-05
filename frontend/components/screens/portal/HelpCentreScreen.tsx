"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import {
  searchHelpCentre,
  type HelpArticleSummary,
  type HelpCategory,
} from "@/lib/portal/helpCentre";
import { cn, TOUCH_TARGET } from "@/lib/utils";

/** How long after the last keystroke the search runs. */
const DEBOUNCE_MS = 250;

/**
 * The help centre, search first.
 *
 * Search is the surface and the category list is the fallback — somebody
 * arrives with a question in words, not with a taxonomy in mind. The
 * categories are always on screen rather than appearing after a failed search,
 * because building the dead end first and the way out second is how a help
 * centre teaches people to give up and write in.
 *
 * Every article here is public and published. That is decided by the QUERY on
 * the server, not by this screen — an internal article is not in the response
 * at all, and asking for one by its exact id gets the same not-found as an id
 * that never existed.
 */
export function HelpCentreScreen() {
  const t = useTranslations("portal.help");

  const [term, setTerm] = useState("");
  const [category, setCategory] = useState<number | null>(null);
  const [articles, setArticles] = useState<HelpArticleSummary[] | null>(null);
  const [categories, setCategories] = useState<HelpCategory[]>([]);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let cancelled = false;

    const timer = setTimeout(() => {
      void (async () => {
        try {
          const body = await searchHelpCentre({
            ...(term.trim() === "" ? {} : { q: term }),
            ...(category === null ? {} : { category_id: category }),
          });

          if (cancelled) return;
          setArticles(body.data);
          setCategories(body.categories);
          setFailed(false);
        } catch {
          if (cancelled) return;
          setArticles([]);
          setFailed(true);
        }
      })();
    }, DEBOUNCE_MS);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [term, category]);

  return (
    <section className="flex w-full max-w-2xl flex-col gap-5" data-slot="help-centre">
      <header className="flex flex-col gap-1">
        <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>
        <p className="text-sm text-fg-muted">{t("hint")}</p>
      </header>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-fg-default">{t("search")}</span>
        <input
          type="search"
          value={term}
          placeholder={t("searchPlaceholder")}
          onChange={(event) => setTerm(event.target.value)}
          className="h-11 rounded-md border border-border-default bg-surface-default px-3 text-sm"
        />
      </label>

      {categories.length > 0 && (
        <nav className="flex flex-wrap gap-2" data-slot="help-categories" aria-label={t("browse")}>
          <button
            type="button"
            onClick={() => setCategory(null)}
            aria-pressed={category === null}
            className={cn(
              "rounded-full border border-border-default px-3 py-1 text-sm",
              category === null && "bg-surface-muted font-medium",
              TOUCH_TARGET,
            )}
          >
            {t("allCategories")}
          </button>

          {categories.map((one) => (
            <button
              key={one.id}
              type="button"
              onClick={() => setCategory(one.id)}
              aria-pressed={category === one.id}
              className={cn(
                "rounded-full border border-border-default px-3 py-1 text-sm",
                category === one.id && "bg-surface-muted font-medium",
                TOUCH_TARGET,
              )}
            >
              {one.name}
            </button>
          ))}
        </nav>
      )}

      {failed && <EmptyState headline={t("failed")} description={t("failedHint")} />}

      {articles !== null && articles.length === 0 && !failed && (
        /*
         * An empty result says so and offers the next action — UX-06. A blank
         * panel is indistinguishable from a broken page, and somebody who
         * cannot tell which will leave.
         */
        <EmptyState
          headline={term.trim() === "" ? t("emptyLibrary") : t("noResults", { term: term.trim() })}
          description={t("noResultsHint")}
          actions={
            <Link href="/portal/submit" className={cn("text-sm underline", TOUCH_TARGET)}>
              {t("askInstead")}
            </Link>
          }
        />
      )}

      {articles !== null && articles.length > 0 && (
        <ul className="flex flex-col gap-2" data-slot="help-results">
          {articles.map((article) => (
            <li key={article.id}>
              <Link
                href={`/portal/help/${article.id}`}
                className={cn(
                  "block rounded-md border border-border-subtle p-3 hover:bg-surface-muted",
                  TOUCH_TARGET,
                )}
              >
                <span className="text-sm font-medium text-fg-default">{article.title}</span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
