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
      // Null until Story 5.3 exists. Rendered as a dash, with the label in
      // place, so nothing moves when the value arrives.
      value: counts?.at_risk ?? null,
      params: { status: LIVE },
    },
    {
      key: "breached",
      label: t("breached"),
      value: counts?.breached ?? null,
      params: { status: LIVE },
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
        const untracked = tile.value === null;

        return (
          <li key={tile.key}>
            <Link
              href={`/tickets?${ticketListQuery(tile.params)}`}
              data-slot="count-tile"
              data-count={tile.key}
              className="flex flex-col gap-1 rounded-md border border-border-default bg-surface-base p-3 hover:bg-surface-hover"
            >
              <span className="text-sm text-fg-muted">{tile.label}</span>

              <span className="text-2xl font-semibold text-fg-default">
                {untracked ? NOT_KNOWN : format.number(tile.value ?? 0)}
              </span>

              {untracked && (
                // Says WHY it is a dash. An unexplained dash reads as a bug.
                <span className="text-xs text-fg-muted">{t("notKnownHint")}</span>
              )}
            </Link>
          </li>
        );
      })}
    </ul>
  );
}
