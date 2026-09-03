"use client";

import { useTranslations } from "next-intl";
import { useCallback } from "react";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { TicketListTable } from "@/components/domain/TicketList/TicketListTable";
import { listTickets, ticketCounts, type TicketListParams } from "@/lib/api/tickets";
import { useFreshQuery } from "@/lib/data/useFreshQuery";

import { CountsStrip } from "./CountsStrip";

export interface AgentHomeScreenProps {
  currentUserId: number | null;
  onOpen: (id: string) => void;
  assigneeNames?: Record<string, string>;
  categoryNames?: Record<string, string>;
}

const REFETCH_MS = 30_000;

/**
 * Where an agent lands, and what they should do next.
 *
 * The counts strip answers "how much is there", the queue answers "what
 * first". Both refresh on the same interval so they cannot disagree with each
 * other on screen, and both keep their last good answer through a failure —
 * an agent working a queue should not watch it empty itself when the wifi
 * blinks.
 *
 * Ordering is deliberate and NOT alphabetical: priority, then age. When the
 * SLA module lands it becomes the first key — the sort is expressed as a
 * server-side sort so that change is one string, not a rewrite.
 */
export function AgentHomeScreen({
  currentUserId,
  onOpen,
  assigneeNames = {},
  categoryNames = {},
}: AgentHomeScreenProps) {
  const t = useTranslations("home");
  // The list's own copy for load states, so both surfaces say the same thing.
  const list = useTranslations("tickets");

  const queueParams: TicketListParams = {
    status: ["open", "pending"],
    ...(currentUserId === null ? {} : { assignee_id: [currentUserId] }),
    /*
     * Priority first, then oldest. SLA urgency becomes the leading key in
     * Story 5.3; until the column exists, sorting by it would be sorting by
     * nothing and would silently reorder the queue when it appears.
     */
    sort: "priority",
    direction: "desc",
    per_page: 25,
  };

  const queueFetcher = useCallback(() => listTickets(queueParams), [currentUserId]); // eslint-disable-line react-hooks/exhaustive-deps
  const countsFetcher = useCallback(() => ticketCounts(), []);

  const queue = useFreshQuery(`queue:${currentUserId ?? "anon"}`, queueFetcher, {
    refetchInterval: REFETCH_MS,
    refetchOnWindowFocus: true,
  });

  const counts = useFreshQuery("counts", countsFetcher, {
    refetchInterval: REFETCH_MS,
    refetchOnWindowFocus: true,
  });

  const rows = queue.data?.data ?? [];

  return (
    <div className="flex flex-col gap-6" data-slot="agent-home">
      <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>

      <CountsStrip counts={counts.data} currentUserId={currentUserId} />

      <section className="flex flex-col gap-3">
        <h2 className="text-base font-semibold text-fg-default">{t("queue.title")}</h2>

        {/*
          Quiet, and the rows stay put. A banner that shouted would interrupt
          work over a blip the next interval will fix by itself.
        */}
        {queue.stale && queue.data !== null && (
          <p role="status" className="text-xs text-fg-muted">
            {list("stale")}
          </p>
        )}

        {queue.stale && queue.data === null && (
          <FormAlert tone="error" action={{ label: list("retry"), onSelect: queue.refetch }}>
            {list("loadError")}
          </FormAlert>
        )}

        {rows.length === 0 && !queue.loading ? (
          <EmptyState headline={t("queue.empty")} description={t("queue.emptyBody")} />
        ) : (
          <TicketListTable
            tickets={rows}
            caption={t("queue.title")}
            onOpen={onOpen}
            assigneeNames={assigneeNames}
            categoryNames={categoryNames}
          />
        )}
      </section>
    </div>
  );
}
