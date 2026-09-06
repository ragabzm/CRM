"use client";

import { useTranslations } from "next-intl";
import { useCallback, useState } from "react";

import { DataTable } from "@/components/domain/DataTable/DataTable";
import type { ColumnDef } from "@/components/domain/DataTable/DataTable.types";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import { listExchangeLog, type ExchangeLogRow } from "@/lib/api/admin";
import { useFreshQuery } from "@/lib/data/useFreshQuery";
import { useFormat } from "@/lib/format/useFormat";

/**
 * Every call this product makes to somebody else's system.
 *
 * The question it answers is asked in a hurry — "the sync stopped last night,
 * what happened?" — and a reader who has to know which of four tables to look
 * in has already lost. One log, one place, every integration.
 *
 * The problems-only filter is what people actually reach for, so it is one
 * control rather than something to construct. `abandoned` is included in it
 * deliberately: a run that gave up is the row most worth seeing, and it is
 * indistinguishable from an ordinary failure at a glance.
 */
export function ExchangeLogTable() {
  const t = useTranslations("admin.integrations");
  const format = useFormat();

  const [problemsOnly, setProblemsOnly] = useState(false);

  const fetcher = useCallback(() => listExchangeLog(), []);

  const { data } = useFreshQuery("exchange-log", fetcher, { refetchInterval: 30_000 });

  const all = data?.data ?? [];
  const rows = problemsOnly
    ? all.filter((row) => row.status === "failed" || row.status === "abandoned")
    : all;

  const columns: ColumnDef<ExchangeLogRow>[] = [
    {
      id: "occurred_at",
      header: t("logColumns.occurredAt"),
      identity: true,
      cell: (row) => <time dateTime={row.occurred_at}>{format.dateTime(row.occurred_at)}</time>,
    },
    {
      id: "integration",
      header: t("logColumns.integration"),
      cell: (row) => row.integration,
    },
    {
      id: "direction",
      header: t("logColumns.direction"),
      cell: (row) => row.direction,
    },
    {
      id: "target",
      header: t("logColumns.target"),
      cell: (row) => <span className="break-all">{row.target}</span>,
    },
    {
      id: "status",
      header: t("logColumns.status"),
      cell: (row) => (
        <span
          className={
            row.status === "failed" || row.status === "abandoned"
              ? "text-fg-danger"
              : "text-fg-muted"
          }
        >
          {/*
            `abandoned` gets its own word rather than being folded into
            "failed": the difference an administrator needs is whether
            anything is still going to try again.
          */}
          {t(`status.${row.status}` as "status.failed")}
          {row.response_status === null ? "" : ` · ${String(row.response_status)}`}
        </span>
      ),
    },
    {
      id: "attempt",
      header: t("logColumns.attempt"),
      cell: (row) => String(row.attempt),
    },
    {
      id: "duration",
      header: t("logColumns.duration"),
      // A number that climbs is the first sign of trouble, and it is
      // invisible without this column.
      cell: (row) => (row.duration_ms === null ? "—" : `${row.duration_ms} ms`),
    },
    {
      id: "error",
      header: t("logColumns.error"),
      // The provider's own words. A generic "failed" gives an administrator
      // nothing they can act on.
      cell: (row) => (row.error === null ? "—" : <span className="break-all">{row.error}</span>),
    },
  ];

  return (
    <div data-slot="exchange-log" className="flex flex-col gap-3">
      <SegmentedFilter
        label={t("logFilter")}
        value={problemsOnly ? "problems" : "all"}
        options={[
          { value: "all", label: t("logAll") },
          { value: "problems", label: t("logProblems") },
        ]}
        onChange={(value) => setProblemsOnly(value === "problems")}
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
