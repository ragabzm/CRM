"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { AiLabel } from "@/components/domain/AiLabel/AiLabel";
import {
  assistArticles,
  assistReply,
  assistSummary,
  type SuggestedArticle,
} from "@/lib/api/assist";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface AiSuggestionPanelProps {
  ticketId: string;
  /** Puts a draft into the composer, where the agent edits it before sending. */
  onUseDraft: (text: string) => void;
  /** Inserts an article link into the reply, reusing Story 8.2's insert. */
  onInsertArticle: (article: SuggestedArticle) => void;
}

/**
 * What the machine offers, beside the conversation and never inside it.
 *
 * THE THREAD HAS THREE TREATMENTS — customer message, agent message, internal
 * note — and an AI draft is not a fourth. It lives here and in the composer.
 * A draft rendered in the thread would be a thing that looks like it was said,
 * beside things that were.
 *
 * NOTHING HERE APPLIES ANYTHING. A summary is read. A draft goes into the
 * composer, where the agent edits it and sends it themselves. An article is
 * inserted into a reply they are writing. There is no accept button that
 * commits, no send-on-accept and no auto-send — the agent is the one who
 * decides, every time.
 *
 * ON DEMAND, ALWAYS. Nothing is fetched until somebody asks: no message-count
 * threshold, no automatic generation, and no setting that would create one. A
 * panel that summarised every ticket on open would be a request to a provider
 * nobody asked for, on every ticket, for ever.
 *
 * Every artefact is wrapped in the shared `AiLabel`, so nothing generated
 * reaches a person without saying what it is.
 */
export function AiSuggestionPanel({
  ticketId,
  onUseDraft,
  onInsertArticle,
}: AiSuggestionPanelProps) {
  const t = useTranslations("ticket.assist");

  const [summary, setSummary] = useState<string | null>(null);
  const [drafts, setDrafts] = useState<string[]>([]);
  const [articles, setArticles] = useState<SuggestedArticle[]>([]);
  const [busy, setBusy] = useState<string | null>(null);

  /**
   * Runs one assist and keeps whatever came back.
   *
   * A failure is SILENCE. The provider being down is not something an agent
   * reading a ticket should be told about mid-conversation: there is no error,
   * no toast and no retry control, and the surface is simply absent.
   */
  async function run<T>(key: string, call: () => Promise<T>, keep: (value: T) => void) {
    setBusy(key);

    try {
      keep(await call());
    } catch {
      // Absence, not an error.
    } finally {
      setBusy(null);
    }
  }

  return (
    <section className="flex flex-col gap-4" data-slot="ai-suggestion-panel">
      <h2 className="text-sm font-semibold text-fg-default">{t("title")}</h2>

      <div className="flex flex-col gap-2" data-slot="assist-summary">
        <button
          type="button"
          disabled={busy !== null}
          onClick={() => void run("summary", () => assistSummary(ticketId), setSummary)}
          className={cn("self-start text-xs underline text-fg-default", TOUCH_TARGET)}
          data-slot="assist-summarise"
        >
          {summary === null ? t("summarise") : t("resummarise")}
        </button>

        {summary !== null && (
          <AiLabel>
            <p className="text-sm text-fg-default" dir="auto" data-slot="assist-summary-text">
              {summary}
            </p>
          </AiLabel>
        )}
      </div>

      <div className="flex flex-col gap-2" data-slot="assist-drafts">
        <button
          type="button"
          disabled={busy !== null}
          onClick={() => void run("reply", () => assistReply(ticketId), setDrafts)}
          className={cn("self-start text-xs underline text-fg-default", TOUCH_TARGET)}
          data-slot="assist-draft"
        >
          {t("draft")}
        </button>

        {drafts.length > 0 && (
          <AiLabel>
            <ul className="flex flex-col gap-2">
              {drafts.map((draft, index) => (
                <li key={index} className="flex flex-col gap-1">
                  <p className="text-sm text-fg-default" dir="auto">
                    {draft}
                  </p>

                  <button
                    type="button"
                    onClick={() => onUseDraft(draft)}
                    className={cn("self-start text-xs underline text-fg-muted", TOUCH_TARGET)}
                    data-slot="assist-use-draft"
                  >
                    {/*
                      "Put it in the composer", not "Send". The wording is the
                      guarantee: an agent reads it, edits it, and sends it
                      themselves — there is no path from here to a customer.
                    */}
                    {t("useDraft")}
                  </button>
                </li>
              ))}
            </ul>
          </AiLabel>
        )}
      </div>

      <div className="flex flex-col gap-2" data-slot="assist-articles">
        <button
          type="button"
          disabled={busy !== null}
          onClick={() => void run("articles", () => assistArticles(ticketId), setArticles)}
          className={cn("self-start text-xs underline text-fg-default", TOUCH_TARGET)}
          data-slot="assist-find-articles"
        >
          {t("findArticles")}
        </button>

        {articles.length > 0 && (
          <AiLabel>
            <ul className="flex flex-col gap-1">
              {articles.map((article) => (
                <li key={article.id} className="flex flex-wrap items-center gap-2">
                  <span className="flex-1 text-sm text-fg-default" dir="auto">
                    {article.title}
                  </span>

                  {article.internal_only && (
                    /*
                      Said out loud. An internal article is in scope for an
                      agent and must never be pasted to a customer — and the
                      one thing standing between those is the agent knowing
                      which it is.
                    */
                    <span className="text-xs text-fg-muted" data-slot="assist-internal">
                      {t("internalOnly")}
                    </span>
                  )}

                  <button
                    type="button"
                    onClick={() => onInsertArticle(article)}
                    className={cn("text-xs underline text-fg-muted", TOUCH_TARGET)}
                    data-slot="assist-insert-article"
                  >
                    {t("insert")}
                  </button>
                </li>
              ))}
            </ul>
          </AiLabel>
        )}
      </div>
    </section>
  );
}
