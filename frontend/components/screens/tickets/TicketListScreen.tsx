"use client";

import { useTranslations } from "next-intl";
import { useCallback, useMemo } from "react";

import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import { TicketListTable } from "@/components/domain/TicketList/TicketListTable";
import type { SortState } from "@/components/domain/DataTable/DataTable.types";
import { listTickets, ticketListQuery, type TicketListParams } from "@/lib/api/tickets";
import { useFreshQuery } from "@/lib/data/useFreshQuery";

export interface TicketListScreenProps {
  /** The filters, read from the URL by the page. */
  params: TicketListParams;
  onParamsChange: (params: TicketListParams) => void;
  onOpen: (id: string) => void;
}

/** How often the list catches up with the queue while an agent is looking at it. */
const REFETCH_MS = 30_000;

/**
 * The ticket list.
 *
 * Every filter lives in the URL and nowhere else. That is what makes a count
 * tile on Home a real link — the URL it points at IS the filter — and what lets
 * an agent send a colleague exactly what they are looking at. There is no saved
 * view, no localStorage, and no state that survives a reload the address bar
 * cannot explain.
 */
export function TicketListScreen({ params, onParamsChange, onOpen }: TicketListScreenProps) {
  const t = useTranslations("tickets");

  // The URL is the cache key: a filter change is a different query, and a
  // result for the previous one must not repaint the table.
  const key = ticketListQuery(params);

  const fetcher = useCallback(() => listTickets(params), [key]); // eslint-disable-line react-hooks/exhaustive-deps

  const { data, loading, stale, status, refetch } = useFreshQuery(key, fetcher, {
    refetchInterval: REFETCH_MS,
    refetchOnWindowFocus: true,
  });

  const sort: SortState = useMemo(
    () =>
      params.sort === undefined
        ? null
        : { column: params.sort, direction: params.direction ?? "desc" },
    [params.sort, params.direction],
  );

  if (status === 403) {
    return <ForbiddenState headline={t("forbidden.title")} description={t("forbidden.body")} />;
  }

  // Only when nothing has ever arrived. A failed refetch keeps the table.
  if (data === null && stale) {
    return (
      <FormAlert tone="error" action={{ label: t("retry"), onSelect: refetch }}>
        {t("loadError")}
      </FormAlert>
    );
  }

  return (
    <div className="flex flex-col gap-4" data-slot="ticket-list">
      <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>

      <div className="flex flex-wrap gap-4">
        <SegmentedFilter
          label={t("filters.status")}
          value={(params.status ?? []).join(",") || "any"}
          options={[
            { value: "any", label: t("filters.any") },
            { value: "open", label: "open" },
            { value: "pending", label: "pending" },
            { value: "closed", label: "closed" },
          ]}
          onChange={(value) =>
            onParamsChange({ ...params, status: value === "any" ? [] : [value] })
          }
        />

        <SegmentedFilter
          label={t("filters.assignee")}
          value={(params.assignee_id ?? []).join(",") || "any"}
          options={[
            { value: "any", label: t("filters.any") },
            { value: "unassigned", label: t("filters.unassigned") },
          ]}
          onChange={(value) =>
            onParamsChange({
              ...params,
              assignee_id: value === "any" ? [] : ["unassigned"],
            })
          }
        />
      </div>

      {/*
        Said quietly, and the rows stay. An agent working a queue over a flaky
        connection should not watch their table empty itself every thirty
        seconds.
      */}
      {stale && data !== null && (
        <p role="status" className="text-xs text-fg-muted">
          {t("stale")}
        </p>
      )}

      <TicketListTable
        tickets={data?.data ?? []}
        caption={t("title")}
        search={params.q ?? ""}
        onSearchChange={(q) => onParamsChange({ ...params, q, page: 1 })}
        sort={sort}
        onSortChange={(next) => {
          // Clearing a sort REMOVES the keys rather than blanking them: the
          // URL should stop mentioning a sort, not carry an empty one that a
          // reader has to interpret.
          const rest: TicketListParams = { ...params };

          delete rest.sort;
          delete rest.direction;

          onParamsChange(
            next === null ? rest : { ...rest, sort: next.column, direction: next.direction },
          );
        }}
        onOpen={onOpen}
        // From the same response as the rows — see AgentHomeScreen for why
        // these are not props.
        assigneeNames={data?.included?.assignees ?? {}}
        categoryNames={data?.included?.categories ?? {}}
      />

      {loading && data === null && <p role="status">{t("title")}</p>}
    </div>
  );
}
