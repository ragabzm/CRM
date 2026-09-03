"use client";

import { useTranslations } from "next-intl";
import { useCallback, useState } from "react";

import { DataTable } from "@/components/domain/DataTable/DataTable";
import type { ColumnDef } from "@/components/domain/DataTable/DataTable.types";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { RowActions } from "@/components/domain/RowActions/RowActions";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import {
  getQuarantinedMail,
  listQuarantine,
  replayQuarantinedMail,
  type QuarantinedMail,
} from "@/lib/api/admin";
import { useFreshQuery } from "@/lib/data/useFreshQuery";
import { useFormat } from "@/lib/format/useFormat";

/**
 * The mail nobody could turn into a ticket.
 *
 * This list is the only place that fact is visible. A customer who emails
 * support and hears nothing has been ignored, whatever the technical reason,
 * and without a surface the silence is invisible on both sides — the customer
 * waits, and nobody here has any reason to look.
 *
 * Outstanding first, and outstanding-only by default: a list that mixes handled
 * and unhandled becomes a list nobody reads to the bottom of.
 */
export function MailQuarantineTable() {
  const t = useTranslations("admin.email");
  const format = useFormat();

  const [outstandingOnly, setOutstandingOnly] = useState(true);
  const [raw, setRaw] = useState<{ id: string; text: string } | null>(null);
  const [outcome, setOutcome] = useState<{ ok: boolean; text: string } | null>(null);

  const fetcher = useCallback(() => listQuarantine({ outstandingOnly }), [outstandingOnly]);

  const { data, refetch } = useFreshQuery(outstandingOnly ? "outstanding" : "all", fetcher, {
    refetchInterval: 60_000,
  });

  const rows = data?.data ?? [];

  async function replay(row: QuarantinedMail) {
    setOutcome(null);

    try {
      const result = await replayQuarantinedMail(row.id);

      setOutcome(
        result.status === "accepted"
          ? { ok: true, text: t("replayed") }
          : // Still unreadable. Said plainly rather than reported as success,
            // because the message is still not on anyone's desk.
            { ok: false, text: `${t("replayFailed")} ${result.reason ?? ""}`.trim() },
      );

      refetch();
    } catch {
      setOutcome({ ok: false, text: t("replayFailed") });
    }
  }

  async function showRaw(row: QuarantinedMail) {
    try {
      const detail = await getQuarantinedMail(row.id);

      setRaw({ id: row.id, text: detail.raw });
    } catch {
      setOutcome({ ok: false, text: t("replayFailed") });
    }
  }

  const columns: ColumnDef<QuarantinedMail>[] = [
    {
      id: "received_at",
      header: t("quarantineColumns.receivedAt"),
      identity: true,
      cell: (row) =>
        row.received_at === null ? (
          "—"
        ) : (
          <time dateTime={row.received_at}>{format.dateTime(row.received_at)}</time>
        ),
    },
    {
      id: "from_address",
      header: t("quarantineColumns.from"),
      // An address is an identifier, not prose.
      cell: (row) => (
        <bdi dir="ltr" className="num">
          {row.from_address ?? "—"}
        </bdi>
      ),
    },
    {
      id: "subject",
      header: t("quarantineColumns.subject"),
      cell: (row) => (
        <span dir="auto" className="break-words">
          {row.subject ?? "—"}
        </span>
      ),
    },
    {
      id: "reason",
      header: t("quarantineColumns.reason"),
      // Wrapped, not clipped: the useful part of a parser error is often at
      // the end.
      cell: (row) => (
        <span dir="auto" className="break-words">
          {row.reason}
        </span>
      ),
    },
    {
      id: "raw_bytes",
      header: t("quarantineColumns.size"),
      type: "number",
      cell: (row) => format.fileSize(row.raw_bytes),
    },
    {
      id: "state",
      header: t("quarantineColumns.state"),
      cell: (row) =>
        row.resolved_at === null ? t("quarantineUnresolved") : t("quarantineResolved"),
    },
    {
      id: "actions",
      header: "",
      type: "action",
      cell: (row) => (
        <RowActions
          rowLabel={row.subject ?? row.id}
          actions={[
            { id: "raw", label: t("viewRaw"), onSelect: () => void showRaw(row) },
            ...(row.resolved_at === null
              ? [{ id: "replay", label: t("replay"), onSelect: () => void replay(row) }]
              : []),
          ]}
        />
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-3" data-slot="mail-quarantine">
      <SegmentedFilter
        label={t("quarantineOutstanding")}
        value={outstandingOnly ? "outstanding" : "all"}
        options={[
          { value: "outstanding", label: t("quarantineOutstanding") },
          { value: "all", label: t("quarantineAll") },
        ]}
        onChange={(value) => setOutstandingOnly(value === "outstanding")}
      />

      {outcome !== null && (
        <FormAlert tone={outcome.ok ? "success" : "error"}>{outcome.text}</FormAlert>
      )}

      <DataTable
        columns={columns}
        rows={rows}
        getRowId={(row) => row.id}
        caption={t("quarantine")}
        emptyState={
          <EmptyState headline={t("quarantineEmpty")} description={t("quarantineEmptyBody")} />
        }
      />

      {raw !== null && (
        <div className="flex flex-col gap-2">
          <h3 className="text-sm font-medium text-fg-default">{t("viewRaw")}</h3>

          {/*
            Monospaced and scrollable in its own box. These are RFC 5322 bytes,
            not prose: reflowing them hides exactly the folded header or stray
            byte somebody opened this to find.
          */}
          <pre
            dir="ltr"
            className="max-h-96 overflow-auto rounded-md border border-border-default bg-surface-base p-3 text-xs"
          >
            {raw.text}
          </pre>
        </div>
      )}
    </div>
  );
}
