"use client";

import { useTranslations } from "next-intl";
import { useCallback, useState } from "react";

import { DataTable } from "@/components/domain/DataTable/DataTable";
import type { ColumnDef } from "@/components/domain/DataTable/DataTable.types";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import { listMailLog, type MailLogRow } from "@/lib/api/admin";
import { useFreshQuery } from "@/lib/data/useFreshQuery";
import { useFormat } from "@/lib/format/useFormat";

/**
 * What the mail channel has been doing.
 *
 * The question it answers is "did that reply actually leave?" — asked by an
 * agent met with silence, and by an administrator whose provider has started
 * throttling. Without it the only evidence is the customer's word and the
 * provider's own dashboard, and neither is to hand at the moment it is needed.
 *
 * The failed-only filter is the one people reach for, so it is one control
 * rather than something to construct.
 */
export function MailLogTable() {
  const t = useTranslations("admin.email");
  const format = useFormat();

  const [failedOnly, setFailedOnly] = useState(false);

  const fetcher = useCallback(
    () => listMailLog(failedOnly ? { status: "failed" } : {}),
    [failedOnly],
  );

  const { data } = useFreshQuery(failedOnly ? "failed" : "all", fetcher, {
    refetchInterval: 30_000,
  });

  const rows = data?.data ?? [];

  const columns: ColumnDef<MailLogRow>[] = [
    {
      id: "occurred_at",
      header: t("logColumns.occurredAt"),
      identity: true,
      cell: (row) =>
        row.occurred_at === null ? (
          "—"
        ) : (
          <time dateTime={row.occurred_at}>{format.dateTime(row.occurred_at)}</time>
        ),
    },
    {
      id: "direction",
      header: t("logColumns.direction"),
      cell: (row) => row.direction,
    },
    {
      id: "address",
      header: t("logColumns.address"),
      // An address is an identifier, not prose: forced LTR so it reads the same
      // in both writing directions.
      cell: (row) => (
        <bdi dir="ltr" className="num">
          {row.address}
        </bdi>
      ),
    },
    {
      id: "subject",
      header: t("logColumns.subject"),
      cell: (row) => (
        <span dir="auto" className="break-words">
          {row.subject ?? "—"}
        </span>
      ),
    },
    {
      id: "status",
      header: t("logColumns.status"),
      cell: (row) => row.status,
    },
    {
      id: "provider",
      header: t("logColumns.provider"),
      cell: (row) => row.provider,
    },
    {
      id: "duration_ms",
      header: t("logColumns.duration"),
      type: "number",
      // A number that climbs is the first sign of trouble, and it is invisible
      // without this column.
      cell: (row) => (row.duration_ms === null ? "—" : `${row.duration_ms} ms`),
    },
    {
      id: "error",
      header: t("logColumns.error"),
      // The provider's own words, wrapped rather than clipped: the useful part
      // of a provider error is often at the end.
      cell: (row) => (
        <span dir="auto" className="break-words">
          {row.error ?? "—"}
        </span>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-3" data-slot="mail-log">
      <SegmentedFilter
        label={t("logFilter.all")}
        value={failedOnly ? "failed" : "all"}
        options={[
          { value: "all", label: t("logFilter.all") },
          { value: "failed", label: t("logFilter.failed") },
        ]}
        onChange={(value) => setFailedOnly(value === "failed")}
      />

      <DataTable
        columns={columns}
        rows={rows}
        getRowId={(row) => row.id}
        caption={t("log")}
        emptyState={<EmptyState headline={t("logEmpty")} description={t("logEmptyBody")} />}
      />
    </div>
  );
}
