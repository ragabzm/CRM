"use client";

import { ArrowDownLeft, ArrowUpRight, TicketIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { useCallback, useEffect, useRef, useState } from "react";

import { BidiValue } from "@/components/domain/BidiValue/BidiValue";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { Button } from "@/components/ui/button";
import { listCustomerTimeline, type TimelineEntry, type TimelineKind } from "@/lib/api/customers";
import { ApiError } from "@/lib/api/request";
import { useFormat } from "@/lib/format/useFormat";

import { useTimelineNavMemory } from "./useTimelineNavMemory";

export interface CustomerTimelineProps {
  customerId: string;
  /** Opens the ticket a row refers to. */
  onOpenTicket: (ticketId: string) => void;
}

/** Glyph per kind, so the row survives greyscale — UX-03. */
const GLYPH: Record<TimelineKind, typeof TicketIcon> = {
  ticket_opened: TicketIcon,
  message_inbound: ArrowDownLeft,
  message_outbound: ArrowUpRight,
};

const LABEL_KEY: Record<TimelineKind, string> = {
  ticket_opened: "entry.ticketOpened",
  message_inbound: "entry.messageInbound",
  message_outbound: "entry.messageOutbound",
};

/**
 * Everything that has happened for one customer, newest first.
 *
 * Explicit "Load more", never infinite scroll. Auto-loading on scroll strands a
 * keyboard user — the button they are tabbing towards keeps moving — and on a
 * phone it means the page never stops growing, so nothing below it is ever
 * reachable.
 *
 * No channel filter, no date filter, no lane markers. One list, read top to
 * bottom.
 */
export function CustomerTimeline({ customerId, onOpenTicket }: CustomerTimelineProps) {
  const t = useTranslations("timeline");
  const format = useFormat();
  const { remember, recall } = useTimelineNavMemory(customerId);

  const [entries, setEntries] = useState<TimelineEntry[]>([]);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(true);
  const [forbidden, setForbidden] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [announcement, setAnnouncement] = useState("");

  const listRef = useRef<HTMLOListElement | null>(null);
  const cursors = useRef<Array<string | null>>([]);
  const restoring = useRef(false);

  const fetchPage = useCallback(
    async (from: string | null, append: boolean) => {
      /*
       * Yield first, so the flags land in a microtask rather than in the
       * effect body that started this. Setting them synchronously there costs
       * an extra render before the request has even left.
       */
      await Promise.resolve();

      setLoading(true);
      setError(null);

      try {
        const page = await listCustomerTimeline(customerId, { cursor: from });

        setEntries((current) => (append ? [...current, ...page.data] : page.data));
        setCursor(page.next_cursor);
        setHasMore(page.has_more);
        cursors.current = append ? [...cursors.current, from] : [from];

        if (append) {
          // Announced, because appending below the fold is invisible to a
          // screen reader otherwise — the button simply stops doing anything.
          setAnnouncement(t("announced", { count: page.data.length }));
        }

        return page.next_cursor;
      } catch (caught) {
        if (caught instanceof ApiError && caught.status === 403) {
          setForbidden(true);

          return null;
        }

        setError(t("error.body"));

        return null;
      } finally {
        setLoading(false);
      }
    },
    [customerId, t],
  );

  /**
   * Replays the pages the reader had already loaded, then puts them back.
   *
   * Replaying rather than storing the rows: the entries may have changed since,
   * and showing a stale copy of a customer's history is worse than a moment's
   * loading.
   */
  const restore = useCallback(async () => {
    const memory = recall();

    if (memory === null) {
      await fetchPage(null, false);

      return;
    }

    restoring.current = true;

    let next: string | null = null;

    for (let page = 0; page < memory.cursors.length; page++) {
      next = await fetchPage(page === 0 ? null : next, page > 0);

      if (next === null) break;
    }

    restoring.current = false;

    // After paint, or the list has no height to scroll within yet.
    requestAnimationFrame(() => {
      const list = listRef.current;

      if (list === null) return;

      list.scrollTop = memory.scrollTop;

      if (memory.focusedEntryId !== null) {
        list
          .querySelector<HTMLElement>(`[data-entry-id="${memory.focusedEntryId}"] button`)
          ?.focus();
      }
    });
  }, [recall, fetchPage]);

  useEffect(() => {
    /*
     * Scheduled, not performed here. Restoring touches several pieces of state
     * and would otherwise cascade renders straight out of the effect body —
     * the same shape the notes and attachments lanes use.
     *
     * Once per customer: `restore` closes over stable callbacks.
     */
    Promise.resolve().then(restore).catch(() => undefined);
  }, [restore]);

  function openTicket(entry: TimelineEntry) {
    // Saved BEFORE navigating: once the route changes this component is gone
    // and there is nothing left to read the scroll position from.
    remember({
      scrollTop: listRef.current?.scrollTop ?? 0,
      focusedEntryId: entry.id,
      cursors: cursors.current,
    });

    onOpenTicket(entry.ticket_id);
  }

  if (forbidden) {
    return <ForbiddenState headline={t("forbidden.title")} description={t("forbidden.body")} />;
  }

  return (
    <section className="flex flex-col gap-4" data-slot="customer-timeline">
      <h2 className="text-base font-semibold text-fg-default">{t("title")}</h2>

      {/* Polite: loading more is the reader's own action, not an interruption. */}
      <p role="status" aria-live="polite" className="sr-only">
        {announcement}
      </p>

      {error && (
        <FormAlert tone="error">
          <span className="flex flex-wrap items-center gap-3">
            <span>{error}</span>
            <Button variant="secondary" onClick={() => void fetchPage(null, false)}>
              {t("error.retry")}
            </Button>
          </span>
        </FormAlert>
      )}

      {entries.length === 0 && !loading && !error ? (
        <EmptyState headline={t("empty.title")} description={t("empty.body")} />
      ) : (
        <ol
          ref={listRef}
          aria-label={t("title")}
          className="flex max-h-[32rem] flex-col gap-2 overflow-y-auto"
        >
          {entries.map((entry) => {
            const Glyph = GLYPH[entry.kind];
            const label = t(LABEL_KEY[entry.kind]);

            return (
              <li
                key={entry.id}
                data-entry-id={entry.id}
                data-kind={entry.kind}
                className="flex flex-col gap-1 rounded-md border border-border-default bg-surface-base p-3"
              >
                <div className="flex flex-wrap items-center gap-2">
                  {/* Labelled, not decorative: the kind is the first thing a
                      screen-reader user needs and colour alone cannot say it. */}
                  <Glyph aria-hidden="true" className="size-4 shrink-0 text-fg-muted" />
                  <span className="text-sm font-medium text-fg-default">{label}</span>

                  <Button
                    variant="ghost"
                    className="h-auto px-2 py-0.5 text-xs"
                    aria-label={t("openTicket", { reference: entry.ticket_ref })}
                    onClick={() => openTicket(entry)}
                  >
                    {/* A Latin reference inside Arabic prose reverses without
                        this isolation. */}
                    <BidiValue>{entry.ticket_ref}</BidiValue>
                  </Button>

                  {/* Start-aligned in RTL by logical property, not by mirroring
                      code. Gregorian + Western digits come from useFormat. */}
                  <time
                    dateTime={entry.occurred_at}
                    className="num ms-auto text-xs text-fg-muted"
                  >
                    {format.dateTime(entry.occurred_at)}
                  </time>
                </div>

                {entry.preview !== null && (
                  /*
                   * Wraps rather than clipping. `truncate` at 375 px would hide
                   * content with no way to reach it — the story forbids silent
                   * truncation, and the server already shortened this to a
                   * preview length that fits two lines.
                   */
                  <p className="whitespace-pre-wrap break-words text-sm text-fg-muted">
                    <BidiValue as="span">{entry.preview}</BidiValue>
                  </p>
                )}
              </li>
            );
          })}
        </ol>
      )}

      {hasMore && (
        <Button
          variant="secondary"
          className="w-fit"
          disabled={loading}
          onClick={() => void fetchPage(cursor, true)}
        >
          {loading ? t("loading") : t("loadMore")}
        </Button>
      )}
    </section>
  );
}
