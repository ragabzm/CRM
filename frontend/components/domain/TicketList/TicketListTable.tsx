"use client";

import Link from "next/link";
import { useTranslations } from "next-intl";

import { AvatarChip } from "@/components/domain/AvatarChip/AvatarChip";
import { BidiValue } from "@/components/domain/BidiValue/BidiValue";
import { DataTable } from "@/components/domain/DataTable/DataTable";
import type { ColumnDef, SortState } from "@/components/domain/DataTable/DataTable.types";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { RowActions } from "@/components/domain/RowActions/RowActions";
import { SlaIndicator } from "@/components/domain/SlaIndicator/SlaIndicator";
import { StatusBadge, type TicketStatusName } from "@/components/domain/StatusBadge/StatusBadge";
import { UrgencyMeter, type UrgencyName } from "@/components/domain/UrgencyMeter/UrgencyMeter";
import type { Ticket } from "@/lib/api/tickets";
import { useFormat } from "@/lib/format/useFormat";

/** What "not tracked yet" looks like. A rendering choice, not translatable copy. */
export const NOT_KNOWN = "—";

export interface TicketListTableProps {
  tickets: Ticket[];
  caption: string;
  search?: string;
  onSearchChange?: (value: string) => void;
  sort?: SortState;
  onSortChange?: (sort: SortState) => void;
  onOpen: (id: string) => void;
  assigneeNames: Record<string, string>;
  categoryNames: Record<string, string>;
}

/**
 * The ticket rows, shared by the list and the home queue.
 *
 * One table definition rather than two, so a column added to the list cannot
 * quietly go missing from home — and so both collapse the same way on a phone.
 *
 * Columns are ordered by how much they identify the row, which is the order
 * `DataTable` folds them in: reference and subject survive to the narrowest
 * screen, category and updated-at go first.
 */
export function TicketListTable({
  tickets,
  caption,
  search,
  onSearchChange,
  sort,
  onSortChange,
  onOpen,
  assigneeNames,
  categoryNames,
}: TicketListTableProps) {
  const t = useTranslations("tickets");
  const tChannel = useTranslations("tickets.channel");
  const format = useFormat();

  const columns: ColumnDef<Ticket>[] = [
    {
      id: "subject",
      header: t("columns.subject"),
      identity: true,
      /*
       * TWO LINES, and the reference no longer has a column of its own.
       *
       * The row used to spread reference, subject, status, priority, SLA,
       * assignee, category and updated-at across eight columns of equal
       * weight, so nothing said which row this was — the eye had to read all
       * of them. The design puts the subject first and everything that only
       * IDENTIFIES the row underneath it in smaller, quieter type.
       */
      cell: (ticket) => (
        <span className="flex min-w-0 flex-col gap-0.5">
          <Link
            href={`/tickets/${ticket.id}`}
            onClick={(event) => {
              /*
               * Anything that means "somewhere else" is left to the browser:
               * a modified click, or the middle button. Only a plain left
               * click goes to the router.
               */
              if (event.defaultPrevented) return;
              if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
              if (event.button !== 0) return;

              event.preventDefault();
              onOpen(ticket.id);
            }}
            dir="auto"
            className="rounded-sm font-medium break-words text-fg-default underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-border-focus"
          >
            {ticket.subject}
          </Link>

          <span className="flex flex-wrap items-center gap-x-1.5 text-xs text-fg-subtle">
            {/* An identifier, forced LTR so it reads the same in both
                writing directions. */}
            <BidiValue>{ticket.reference}</BidiValue>

            <span aria-hidden="true">·</span>
            {/* Where it came from. The mockup carries it on this line and the
                working list did not show it at all. */}
            <span>{tChannel(ticket.channel)}</span>
          </span>
        </span>
      ),
    },
    {
      id: "status",
      header: t("columns.status"),
      sortable: true,
      /*
       * The word, not the enum. `tickets.status.*` and `tickets.priority.*`
       * were translated into both languages and then read by nothing — the
       * table printed the raw API value, so an Arabic reader saw `open` and
       * `urgent` in lowercase Latin on the busiest screen in the product.
       */
      cell: (ticket) => <StatusBadge status={ticket.status as TicketStatusName} />,
    },
    {
      id: "priority",
      /*
       * FOLDS below desktop. `DataTable` reprints a folded value, labelled,
       * under the identity cell — the value MOVES, it is never dropped, which
       * is the difference the design draws between folding and truncating.
       *
       * Nothing was marked, so at 390px the table stayed eight columns wide
       * inside a container that hid the overflow: the row was silently cut
       * off at the screen edge with no cue and no way to scroll to it.
       */
      secondary: true,
      header: t("columns.priority"),
      sortable: true,
      cell: (ticket) => <UrgencyMeter priority={ticket.priority as UrgencyName} />,
    },
    {
      id: "sla",
      header: t("columns.sla"),
      /*
       * The column that Story 4.5 reserved and drew as a dash. It now carries
       * the real reading — and still draws a dash when the engine is not
       * tracking, because "we do not know" and "fine" are different answers.
       */
      cell: (ticket) => <SlaIndicator sla={ticket.sla ?? null} />,
    },
    {
      id: "assignee",
      secondary: true,
      header: t("columns.assignee"),
      cell: (ticket) => (
        <AvatarChip
          name={
            ticket.assignee_id === null
              ? null
              : (assigneeNames[String(ticket.assignee_id)] ?? NOT_KNOWN)
          }
          unassignedLabel={t("filters.unassigned")}
        />
      ),
    },
    {
      id: "category",
      secondary: true,
      header: t("columns.category"),
      cell: (ticket) =>
        ticket.category_id === null ? (
          NOT_KNOWN
        ) : (
          <span dir="auto">{categoryNames[String(ticket.category_id)] ?? NOT_KNOWN}</span>
        ),
    },
    {
      id: "updated_at",
      secondary: true,
      header: t("columns.updated"),
      sortable: true,
      /*
       * Relative, with the exact moment in the tooltip.
       *
       * "12m ago" answers the question an agent is actually asking — is this
       * moving? — in a glance. "Sep 3, 2026, 7:23 PM" makes them subtract.
       * The absolute time stays reachable, because a supervisor writing an
       * incident note needs it.
       */
      cell: (ticket) =>
        ticket.updated_at === null ? (
          NOT_KNOWN
        ) : (
          <time
            dateTime={ticket.updated_at}
            title={format.dateTime(ticket.updated_at)}
            className="text-fg-muted"
          >
            {format.relativeTime(ticket.updated_at, new Date())}
          </time>
        ),
    },
    {
      id: "actions",
      header: "",
      // `type: "action"` is what makes DataTable give the header an accessible
      // name of its own; without it axe reports an empty column header.
      type: "action",
      cell: (ticket) => (
        <RowActions
          rowLabel={ticket.reference}
          actions={[{ id: "open", label: t("open"), onSelect: () => onOpen(ticket.id) }]}
        />
      ),
    },
  ];

  return (
    <DataTable
      columns={columns}
      rows={tickets}
      getRowId={(ticket) => ticket.id}
      caption={caption}
      {...(search !== undefined ? { search } : {})}
      {...(onSearchChange !== undefined ? { onSearchChange } : {})}
      {...(sort !== undefined ? { sort } : {})}
      {...(onSortChange !== undefined ? { onSortChange } : {})}
      emptyState={<EmptyState headline={t("empty.title")} description={t("empty.body")} />}
    />
  );
}
