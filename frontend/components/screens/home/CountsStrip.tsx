"use client";

import Link from "next/link";
import { useTranslations } from "next-intl";

import { NOT_KNOWN } from "@/components/domain/TicketList/TicketListTable";
import { ticketListQuery, type TicketCounts, type TicketListParams } from "@/lib/api/tickets";
import { useFormat } from "@/lib/format/useFormat";

export interface CountsStripProps {
  counts: TicketCounts | null;
  /** The signed-in user, so "assigned to me" links to a real id. */
  currentUserId: number | null;
}

/** The statuses a ticket is in while it still needs someone. */
const LIVE: string[] = ["open", "pending"];

/**
 * The five numbers, and five links.
 *
 * EVERY tile is an anchor to the list, carrying the exact filter the number was
 * counted with. A figure with no click-through is a figure an agent can only
 * stare at — and worse, one nobody can check. Because the link and the count
 * are built from the same params, a tile that disagrees with the list it opens
 * is a bug that shows itself immediately.
 *
 * A real `<a>`, not a button with a router push: it is navigation, so it should
 * open in a new tab on a middle click, be copyable, and be reachable by
 * keyboard without any of that being re-implemented.
 */
export function CountsStrip({ counts, currentUserId }: CountsStripProps) {
  /*
   * `counts === null` means the request has not answered; a null FIELD means
   * the answer arrived and said "not tracked". The strip used to conflate
   * them, so on first paint all five tiles read "Not tracked yet" — a claim
   * about a feature that works — and then corrected themselves a second later.
   */
  const waiting = counts === null;
  const t = useTranslations("home.counts");
  const format = useFormat();

  const tiles: Array<{
    key: string;
    label: string;
    value: number | null;
    params: TicketListParams;
  }> = [
    {
      key: "assignedToMe",
      label: t("assignedToMe"),
      value: counts?.assigned_to_me ?? null,
      params: {
        status: LIVE,
        ...(currentUserId === null ? {} : { assignee_id: [currentUserId] }),
      },
    },
    {
      key: "unassigned",
      label: t("unassigned"),
      value: counts?.unassigned ?? null,
      params: { status: LIVE, assignee_id: ["unassigned"] },
    },
    {
      key: "atRisk",
      label: t("atRisk"),
      /*
       * Still null when nothing is tracking — rendered as a dash with a line
       * saying why — but a real number as soon as the engine is on.
       */
      value: counts?.at_risk ?? null,
      /*
       * Carries the SLA condition. Both of these tiles used to link to
       * `status=open,pending` with no condition at all, so a figure of 3
       * opened a page of 40 and the number and the page were unrelated.
       */
      params: { status: LIVE, sla_state: "at_risk" },
    },
    {
      key: "breached",
      label: t("breached"),
      value: counts?.breached ?? null,
      params: { status: LIVE, sla_state: "breached" },
    },
    {
      key: "pendingCustomerReply",
      label: t("pendingCustomerReply"),
      value: counts?.pending_customer_reply ?? null,
      params: { status: ["pending"] },
    },
  ];

  return (
    <ul data-slot="counts-strip" className="grid grid-cols-2 gap-3 tablet:grid-cols-5">
      {tiles.map((tile) => {
        const untracked = !waiting && tile.value === null;

        return (
          <li key={tile.key}>
            <Link
              href={`/tickets?${ticketListQuery(tile.params)}`}
              data-slot="count-tile"
              data-count={tile.key}
              /*
               * Looks like what it is. It was always an `<a>`, but with no
               * arrow and no hover treatment it read as a static readout — so
               * the five numbers that are the fastest way into a filtered
               * queue looked like they did nothing.
               */
              className="group flex flex-col gap-1 rounded-md border border-border-default bg-surface-base p-3 transition-colors hover:border-border-strong hover:bg-surface-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-border-focus"
            >
              {/*
                The NUMBER first, large. It used to sit under its own label in
                small type — but the number is the message, and the label only
                says what it counts.
              */}
              <span className="flex items-center gap-2">
                <span className="num text-2xl font-semibold text-fg-default" dir="ltr">
                  {waiting ? (
                    <span
                      aria-hidden="true"
                      className="inline-block h-6 w-8 animate-pulse rounded-sm bg-surface-sunken align-middle"
                    />
                  ) : untracked ? (
                    NOT_KNOWN
                  ) : (
                    format.number(tile.value ?? 0)
                  )}
                </span>

                <span
                  aria-hidden="true"
                  className="ms-auto text-fg-subtle transition-transform group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5"
                >
                  →
                </span>
              </span>

              <span className="text-sm text-fg-muted">{tile.label}</span>

              {untracked && (
                // Says WHY it is a dash. An unexplained dash reads as a bug.
                <span className="text-xs text-fg-muted">{t("notKnownHint")}</span>
              )}

              {waiting && <span className="sr-only">{t("loading")}</span>}
            </Link>
          </li>
        );
      })}
    </ul>
  );
}
