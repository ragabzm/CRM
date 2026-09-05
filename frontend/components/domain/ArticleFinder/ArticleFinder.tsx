"use client";

import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { articleUrl, searchArticles, type ArticleSearchHit } from "@/lib/api/knowledge";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface ArticleFinderProps {
  /**
   * Called with the markdown link when a result is chosen.
   *
   * ONE action. No intermediate dialog, no "copy URL" step: an agent mid-reply
   * who has to open a second window, find the link, copy it and come back has
   * been taken out of the sentence they were writing.
   */
  onInsert: (link: string) => void;
}

/** How long after the last keystroke the search runs. */
const DEBOUNCE_MS = 250;

/**
 * Finding the answer we already wrote, without leaving the ticket.
 *
 * Renders IN PLACE beside the composer. It never navigates and never unmounts
 * the ticket, because the whole value of it is that the half-written reply is
 * still there when the agent comes back to it — and a panel that cost them
 * their draft would be a panel nobody uses twice.
 *
 * Debounced rather than search-on-submit: an agent scanning for an article
 * types three letters and looks. Waiting for Enter makes them decide the
 * search word before they can see whether it worked.
 */
export function ArticleFinder({ onInsert }: ArticleFinderProps) {
  const t = useTranslations("knowledge.finder");

  const [term, setTerm] = useState("");
  const [results, setResults] = useState<ArticleSearchHit[] | null>(null);
  const [searching, setSearching] = useState(false);

  useEffect(() => {
    const query = term.trim();
    let cancelled = false;

    /*
     * Both branches go through the timer, so nothing sets state during the
     * effect itself — React refuses that, and rightly: a synchronous setState
     * here re-renders before the effect has finished and the second render
     * runs the effect again.
     */
    const timer = setTimeout(() => {
      if (query === "") {
        if (!cancelled) {
          setResults(null);
          setSearching(false);
        }

        return;
      }

      if (!cancelled) setSearching(true);

      searchArticles(query)
        .then((hits) => {
          if (!cancelled) setResults(hits);
        })
        .catch(() => {
          /*
           * An empty panel, not an error banner. This is a convenience beside
           * the reply an agent is writing; a failed lookup must not take over
           * the screen they are working on.
           */
          if (!cancelled) setResults([]);
        })
        .finally(() => {
          if (!cancelled) setSearching(false);
        });
    }, DEBOUNCE_MS);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [term]);

  return (
    <section className="flex flex-col gap-2" data-slot="article-finder">
      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-fg-default">{t("label")}</span>
        <input
          type="search"
          value={term}
          placeholder={t("placeholder")}
          onChange={(event) => setTerm(event.target.value)}
          className="h-11 rounded-md border border-border-default bg-surface-default px-3 text-sm"
        />
      </label>

      {searching && results === null && <p className="text-xs text-fg-muted">{t("searching")}</p>}

      {results !== null && results.length === 0 && !searching && (
        /*
         * An empty result is not an empty panel. It says so, and it offers the
         * way on — UX-06.
         */
        <EmptyState
          headline={t("noResults", { term: term.trim() })}
          description={t("noResultsHint")}
        />
      )}

      {results !== null && results.length > 0 && (
        <ul className="flex flex-col gap-1" data-slot="article-results">
          {results.map((hit) => (
            <li key={hit.id}>
              <button
                type="button"
                onClick={() => onInsert(`[${hit.title ?? hit.id}](${articleUrl(hit.id)})`)}
                className={cn(
                  "flex w-full flex-col items-start gap-0.5 rounded-md p-2 text-start hover:bg-surface-muted",
                  TOUCH_TARGET,
                )}
              >
                <span className="text-sm font-medium text-fg-default">{hit.title}</span>

                <span className="flex items-center gap-1.5 text-xs text-fg-subtle">
                  {hit.internal_only && (
                    /*
                     * Said out loud. An agent pasting a link to an INTERNAL
                     * article into a customer reply would be sending a link
                     * that answers with "not available" — and they would not
                     * find out until the customer told them.
                     */
                    <span className="font-semibold text-state-warning" data-slot="internal-marker">
                      {t("internal")}
                    </span>
                  )}

                  {hit.status !== "published" && (
                    <span data-slot="status-marker">{t(`status.${hit.status}`)}</span>
                  )}

                  <span>{t(`type.${hit.type}`)}</span>
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
