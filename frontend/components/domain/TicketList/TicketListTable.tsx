"use client";

import { useTranslations } from "next-intl";

import { BidiValue } from "@/components/domain/BidiValue/BidiValue";
import { DataTable } from "@/components/domain/DataTable/DataTable";
import type { ColumnDef, SortState } from "@/components/domain/DataTable/DataTable.types";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { RowActions } from "@/components/domain/RowActions/RowActions";
import { SlaIndicator } from "@/components/domain/SlaIndicator/SlaIndicator";
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
  const format = useFormat();

  const columns: ColumnDef<Ticket>[] = [
    {
      id: "reference",
      header: t("columns.reference"),
      identity: true,
      sortable: true,
      // An identifier, not prose: forced LTR so it reads the same in both
      // writing directions.
      cell: (ticket) => <BidiValue>{ticket.reference}</BidiValue>,
    },
    {
      id: "subject",
      header: t("columns.subject"),
      // Wraps rather than clipping. A truncated subject hides the words that
      // told the agent what the ticket is.
      cell: (ticket) => (
        <span dir="auto" className="break-words">
          {ticket.subject}
        </span>
      ),
    },
    {
      id: "status",
      header: t("columns.status"),
      sortable: true,
      cell: (ticket) => ticket.status,
    },
    {
      id: "priority",
      header: t("columns.priority"),
      sortable: true,
      cell: (ticket) => ticket.priority,
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
      header: t("columns.assignee"),
      cell: (ticket) =>
        ticket.assignee_id === null ? (
          <span className="text-fg-muted">{t("filters.unassigned")}</span>
        ) : (
          <span dir="auto">{assigneeNames[String(ticket.assignee_id)] ?? NOT_KNOWN}</span>
        ),
    },
    {
      id: "category",
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
      header: t("columns.updated"),
      sortable: true,
      cell: (ticket) =>
        ticket.updated_at === null ? (
          NOT_KNOWN
        ) : (
          <time dateTime={ticket.updated_at}>{format.dateTime(ticket.updated_at)}</time>
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
