"use client";

import { useTranslations } from "next-intl";
import { useCallback, useEffect, useState } from "react";

import { AuditEntryDetail } from "@/components/domain/AuditEntryDetail/AuditEntryDetail";
import { AuditFilterBar } from "@/components/domain/AuditFilterBar/AuditFilterBar";
import { BidiValue } from "@/components/domain/BidiValue/BidiValue";
import { DataTable } from "@/components/domain/DataTable/DataTable";
import type { ColumnDef } from "@/components/domain/DataTable/DataTable.types";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { RowActions } from "@/components/domain/RowActions/RowActions";
import {
  getAuditEntry,
  listAuditEntries,
  type AuditEntryDetail as Entry,
  type AuditEntrySummary,
  type AuditFilters,
  type AuditPage,
} from "@/lib/api/admin";
import { ApiError } from "@/lib/api/request";
import { useFormat } from "@/lib/format/useFormat";

/** `auth.sign_in.succeeded` → `authSignInSucceeded`, the flat message key. */
function messageKeyFor(action: string): string {
  const parts = action.split(/[._]/);

  return (
    parts[0]! +
    parts
      .slice(1)
      .map((part) => part[0]!.toUpperCase() + part.slice(1))
      .join("")
  );
}

/**
 * The audit log.
 *
 * A COMPARATIVE table: horizontal scroll with the actor column pinned, not the
 * folding used by the ticket and customer lists. The difference is what the
 * reader is doing — here they are reading down a column comparing values ("who
 * else touched this?", "what else happened that morning?"), and folding a
 * column away to save width destroys exactly that.
 *
 * There is no edit or delete affordance anywhere on this screen, and the copy
 * near the header says why. Immutability that is only true in the backend is a
 * property nobody reading the screen can rely on.
 */
export function AuditLogScreen() {
  const t = useTranslations("audit");
  const tActions = useTranslations("audit.actions");
  const format = useFormat();

  const [page, setPage] = useState<AuditPage | null>(null);
  const [filters, setFilters] = useState<AuditFilters>({ page: 1 });
  const [forbidden, setForbidden] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [detail, setDetail] = useState<Entry | null>(null);

  useEffect(() => {
    let cancelled = false;

    listAuditEntries(filters)
      .then((result) => {
        if (cancelled) return;
        setPage(result);
        setForbidden(false);
        setError(null);
      })
      .catch((caught: unknown) => {
        if (cancelled) return;

        // A 403 is not an error to retry — it is an answer, and it gets the
        // surface built for answering it.
        if (caught instanceof ApiError && caught.status === 403) {
          setForbidden(true);
          return;
        }

        setError(t("loadError"));
      });

    return () => {
      cancelled = true;
    };
  }, [filters, t]);

  const labelForAction = useCallback(
    (action: string): string => {
      const key = messageKeyFor(action);

      // An action the server records but the console has no copy for still
      // renders — as its raw name, which is worse-looking and still true.
      return tActions.has(key) ? tActions(key) : action;
    },
    [tActions],
  );

  if (forbidden) {
    return <ForbiddenState headline={t("forbidden.title")} description={t("forbidden.body")} />;
  }

  const columns: ColumnDef<AuditEntrySummary>[] = [
    {
      id: "actor",
      header: t("columns.actor"),
      identity: true,
      // Pinned, so the horizontal scroll cannot lose the reader: every other
      // column is meaningless without knowing whose row it is.
      pinned: true,
      cell: (entry) => <span>{entry.actor.label}</span>,
    },
    {
      id: "occurred_at",
      header: t("columns.occurredAt"),
      type: "date",
      cell: (entry) =>
        entry.occurred_at ? <span>{format.dateTime(entry.occurred_at)}</span> : null,
    },
    {
      id: "action",
      header: t("columns.action"),
      cell: (entry) => <span>{labelForAction(entry.action)}</span>,
    },
    {
      id: "target",
      header: t("columns.target"),
      cell: (entry) =>
        entry.target.id ? (
          <BidiValue>
            {entry.target.type ? `${entry.target.type}:${entry.target.id}` : entry.target.id}
          </BidiValue>
        ) : null,
    },
    {
      id: "source_ip",
      header: t("columns.sourceIp"),
      cell: (entry) => (entry.source_ip ? <BidiValue>{entry.source_ip}</BidiValue> : null),
    },
    {
      id: "actions",
      header: "",
      type: "action",
      cell: (entry) => (
        <RowActions
          rowLabel={entry.actor.label}
          // View, and only view. Not a disabled Edit — a greyed-out control
          // invites the question of who is allowed to press it.
          actions={[
            {
              id: "view",
              label: t("detail.open"),
              onSelect: () => {
                void getAuditEntry(entry.id)
                  .then(setDetail)
                  .catch(() => undefined);
              },
            },
          ]}
        />
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-col gap-2">
        <h2 className="text-base font-semibold text-fg-default">{t("title")}</h2>
        <p className="max-w-prose text-sm text-fg-muted">{t("immutabilityNote")}</p>
      </header>

      <AuditFilterBar
        filters={filters}
        onChange={setFilters}
        actions={page?.actions ?? []}
        labelForAction={labelForAction}
      />

      {error && <FormAlert tone="error">{error}</FormAlert>}

      <DataTable
        mode="scroll"
        columns={columns}
        rows={page?.data ?? []}
        getRowId={(entry) => entry.id}
        caption={t("title")}
        page={filters.page ?? 1}
        pageCount={page?.meta.last_page ?? 1}
        onPageChange={(next) => setFilters((current) => ({ ...current, page: next }))}
        emptyState={<EmptyState headline={t("empty.title")} description={t("empty.body")} />}
      />

      <AuditEntryDetail entry={detail} onOpenChange={(open) => !open && setDetail(null)} />
    </div>
  );
}
