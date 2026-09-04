"use client";

import Link from "next/link";
import { useTranslations } from "next-intl";
import { useCallback, useMemo } from "react";

import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { RowSkeleton } from "@/components/domain/RowSkeleton/RowSkeleton";
import { TicketListTable } from "@/components/domain/TicketList/TicketListTable";
import type { SortState } from "@/components/domain/DataTable/DataTable.types";
import {
  listTickets,
  ticketListQuery,
  type SlaStateFilter,
  type TicketListParams,
} from "@/lib/api/tickets";
import { useFreshQuery } from "@/lib/data/useFreshQuery";
import { TOUCH_TARGET, cn } from "@/lib/utils";

interface Named {
  id: number;
  name: string;
}

export interface TicketListScreenProps {
  /** The filters, read from the URL by the page. */
  params: TicketListParams;
  onParamsChange: (params: TicketListParams) => void;
  onOpen: (id: string) => void;
  /**
   * What the category, department and assignee filters can offer.
   *
   * Empty by default so the screen still renders in a test without them —
   * a filter with nothing to choose is hidden rather than shown blank.
   */
  categories?: Named[];
  departments?: Named[];
  assignees?: Named[];
}

/** How often the list catches up with the queue while an agent is looking at it. */
const REFETCH_MS = 30_000;

const STATUSES = ["open", "pending", "resolved", "closed"] as const;
const PRIORITIES = ["low", "normal", "high", "urgent"] as const;

/**
 * The ticket list.
 *
 * Every filter lives in the URL and nowhere else. That is what makes a count
 * tile on Home a real link — the URL it points at IS the filter — and what lets
 * an agent send a colleague exactly what they are looking at. There is no saved
 * view, no localStorage, and no state that survives a reload the address bar
 * cannot explain.
 */
export function TicketListScreen({
  params,
  onParamsChange,
  onOpen,
  categories = [],
  departments = [],
  assignees = [],
}: TicketListScreenProps) {
  const t = useTranslations("tickets");
  const tNew = useTranslations("tickets.new");
  const tStatus = useTranslations("tickets.status");
  const tPriority = useTranslations("tickets.priority");
  const tSla = useTranslations("tickets.sla.state");

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
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>

        {/*
          The only way into the new-ticket form, and the reason it was
          unreachable for two stories: the route existing is not the same as
          somebody being able to find it.

          A real `<a>`, not a button that pushes — it is navigation, so it
          should open in a new tab on a middle click and be copyable, the same
          rule the count tiles follow.
        */}
        <Link
          href="/tickets/new"
          data-slot="new-ticket"
          /*
           * Filled with the accent: this is the primary action of the screen
           * and it was drawn as an outlined secondary, weighted the same as
           * "Columns". The `+` is the other half — it says "make one" without
           * reading the label.
           */
          className={cn(
            "inline-flex items-center gap-1.5 rounded-md bg-accent-default px-3 py-2 text-sm font-semibold text-accent-fg transition-colors hover:bg-accent-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-border-focus",
            TOUCH_TARGET,
          )}
        >
          <span aria-hidden="true">+</span>
          {tNew("title")}
        </Link>
      </div>

      {/*
        ONE mechanism, not two.
        The bar grew to six filters as a mixture: segmented button groups with
        no visible label, and selects with one — so the same strip was read two
        different ways, the word "Any" appeared three times meaning three
        different things, and the groups wrapped onto a second line with
        nothing marking where one ended. Every filter is a labelled select now,
        and what is CHOSEN is repeated underneath as a removable chip.
      */}
      <div className="flex flex-wrap items-end gap-4" data-slot="ticket-filters">
        <ListFilter
          label={t("filters.status")}
          anyLabel={t("filters.any")}
          options={STATUSES.map((value) => ({ value, name: tStatus(value) }))}
          value={params.status?.[0] ?? null}
          onChange={(value) => onParamsChange({ ...params, status: value === null ? [] : [value] })}
        />

        <ListFilter
          label={t("filters.priority")}
          anyLabel={t("filters.any")}
          options={PRIORITIES.map((value) => ({ value, name: tPriority(value) }))}
          value={params.priority?.[0] ?? null}
          onChange={(value) =>
            onParamsChange({ ...params, priority: value === null ? [] : [value] })
          }
        />

        <ListFilter
          label={t("filters.category")}
          anyLabel={t("filters.any")}
          options={categories.map((c) => ({ value: String(c.id), name: c.name }))}
          value={params.category_id?.[0] === undefined ? null : String(params.category_id[0])}
          onChange={(value) =>
            onParamsChange({
              ...params,
              ...(value === null ? { category_id: [] } : { category_id: [Number(value)] }),
            })
          }
        />

        <ListFilter
          label={t("filters.department")}
          anyLabel={t("filters.any")}
          options={departments.map((d) => ({ value: String(d.id), name: d.name }))}
          value={params.department_id?.[0] === undefined ? null : String(params.department_id[0])}
          onChange={(value) =>
            onParamsChange({
              ...params,
              ...(value === null ? { department_id: [] } : { department_id: [Number(value)] }),
            })
          }
        />

        <ListFilter
          label={t("filters.sla")}
          anyLabel={t("filters.any")}
          options={[
            { value: "at_risk", name: tSla("at_risk") },
            { value: "breached", name: tSla("breached") },
          ]}
          value={params.sla_state ?? null}
          onChange={(value) => {
            const rest = { ...params };

            delete rest.sla_state;

            onParamsChange(value === null ? rest : { ...rest, sla_state: value as SlaStateFilter });
          }}
        />

        <ListFilter
          label={t("filters.assignee")}
          anyLabel={t("filters.any")}
          options={[
            { value: "unassigned", name: t("filters.unassigned") },
            ...assignees.map((person) => ({ value: String(person.id), name: person.name })),
          ]}
          value={assigneeValue(params)}
          onChange={(value) =>
            onParamsChange({
              ...params,
              assignee_id:
                value === null ? [] : value === "unassigned" ? ["unassigned"] : [Number(value)],
            })
          }
        />
      </div>

      <ActiveFilters
        params={params}
        onParamsChange={onParamsChange}
        clearLabel={t("filters.clear")}
        labels={{
          status: t("filters.status"),
          priority: t("filters.priority"),
          category: t("filters.category"),
          department: t("filters.department"),
          sla: t("filters.sla"),
          assignee: t("filters.assignee"),
        }}
        names={{
          status: (v) => tStatus(v),
          priority: (v) => tPriority(v),
          sla: (v) => tSla(v),
          category: (v) => categories.find((c) => String(c.id) === v)?.name ?? v,
          department: (v) => departments.find((d) => String(d.id) === v)?.name ?? v,
          assignee: (v) =>
            v === "unassigned"
              ? t("filters.unassigned")
              : (assignees.find((p) => String(p.id) === v)?.name ?? v),
        }}
      />

      {/*
        A skeleton, never the empty state.
        This used to sit BELOW the table and read "Tickets" — the page title
        again — while the table, handed an empty array, drew "No tickets match
        these filters" underneath it. So the first paint of every visit said
        there were no results before the request had answered, then changed
        its mind. Placeholder rows of the right height say "not yet" without
        claiming anything, and stop the page jumping when the data lands.
      */}
      {loading && data === null ? (
        <RowSkeleton label={t("loading")} />
      ) : (
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
      )}

      {/*
        How much of the queue this is.
        The list had no footer at all, so an agent looking at 25 rows could not
        tell whether that was the whole queue or the first page of six — and
        `page` was already in the params, reachable only by editing the URL.
      */}
      {data !== null && data.meta.total > 0 && (
        <Pager
          meta={data.meta}
          onPage={(page) => onParamsChange({ ...params, page })}
          summary={(shown) =>
            t("pager.showing", {
              from: shown.from,
              to: shown.to,
              total: data.meta.total,
            })
          }
          previousLabel={t("pager.previous")}
          nextLabel={t("pager.next")}
        />
      )}
    </div>
  );
}

/**
 * Previous, next, and where you are.
 *
 * Buttons rather than links: the page lives in the URL, but paging is
 * `onParamsChange` like every other filter, so the same `replace` keeps Back
 * out of a walk through six pages of the same queue.
 */
function Pager({
  meta,
  onPage,
  summary,
  previousLabel,
  nextLabel,
}: {
  meta: { total: number; per_page: number; current_page: number; last_page: number };
  onPage: (page: number) => void;
  summary: (shown: { from: number; to: number }) => string;
  previousLabel: string;
  nextLabel: string;
}) {
  const from = (meta.current_page - 1) * meta.per_page + 1;
  const to = Math.min(meta.total, meta.current_page * meta.per_page);

  return (
    <div className="flex flex-wrap items-center justify-between gap-3" data-slot="pager">
      <p className="num text-xs text-fg-muted" dir="auto">
        {summary({ from, to })}
      </p>

      <div className="flex items-center gap-2">
        <button
          type="button"
          disabled={meta.current_page <= 1}
          onClick={() => onPage(meta.current_page - 1)}
          className={cn(
            "rounded-md border border-border-default px-3 py-1.5 text-sm text-fg-default disabled:opacity-50 hover:enabled:bg-surface-hover",
            TOUCH_TARGET,
          )}
        >
          {previousLabel}
        </button>

        <button
          type="button"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
          className={cn(
            "rounded-md border border-border-default px-3 py-1.5 text-sm text-fg-default disabled:opacity-50 hover:enabled:bg-surface-hover",
            TOUCH_TARGET,
          )}
        >
          {nextLabel}
        </button>
      </div>
    </div>
  );
}

/** The current assignee filter as a select value. */
function assigneeValue(params: TicketListParams): string | null {
  const first = params.assignee_id?.[0];

  if (first === undefined) return null;

  return first === "unassigned" ? "unassigned" : String(first);
}

/**
 * What is currently narrowing the list, and how to stop it.
 *
 * A select shows its own value, but six of them side by side do not add up to
 * an answer to "why am I only seeing four tickets?" — the reader has to
 * inspect each control. The chips say it in one line and each one removes
 * itself.
 */
function ActiveFilters({
  params,
  onParamsChange,
  labels,
  names,
  clearLabel,
}: {
  params: TicketListParams;
  onParamsChange: (params: TicketListParams) => void;
  labels: Record<string, string>;
  names: Record<string, (value: string) => string>;
  clearLabel: string;
}) {
  const active: Array<{ id: string; label: string; value: string; clear: () => void }> = [];

  const add = (id: string, value: string | undefined, clear: () => void): void => {
    if (value === undefined) return;

    active.push({
      id,
      label: labels[id] ?? id,
      value: (names[id] ?? ((v: string) => v))(value),
      clear,
    });
  };

  add("status", params.status?.[0], () => onParamsChange({ ...params, status: [] }));
  add("priority", params.priority?.[0], () => onParamsChange({ ...params, priority: [] }));
  add(
    "category",
    params.category_id?.[0] === undefined ? undefined : String(params.category_id[0]),
    () => onParamsChange({ ...params, category_id: [] }),
  );
  add(
    "department",
    params.department_id?.[0] === undefined ? undefined : String(params.department_id[0]),
    () => onParamsChange({ ...params, department_id: [] }),
  );
  add("sla", params.sla_state, () => {
    const rest = { ...params };

    delete rest.sla_state;

    onParamsChange(rest);
  });
  add("assignee", assigneeValue(params) ?? undefined, () =>
    onParamsChange({ ...params, assignee_id: [] }),
  );

  if (active.length === 0) return null;

  return (
    <ul className="flex flex-wrap items-center gap-2" data-slot="active-filters">
      {active.map((chip) => (
        <li key={chip.id}>
          <button
            type="button"
            data-slot="active-filter"
            data-filter={chip.id}
            onClick={chip.clear}
            className={cn(
              "inline-flex items-center gap-2 rounded-full border border-border-default bg-surface-sunken px-3 py-1 text-xs text-fg-default hover:bg-surface-hover",
              TOUCH_TARGET,
            )}
          >
            <span className="text-fg-muted">{chip.label}</span>
            <span dir="auto">{chip.value}</span>
            <span aria-hidden="true">×</span>
            <span className="sr-only">{clearLabel}</span>
          </button>
        </li>
      ))}
    </ul>
  );
}

function ListFilter({
  label,
  anyLabel,
  options,
  value,
  onChange,
}: {
  label: string;
  anyLabel: string;
  options: Array<{ value: string; name: string }>;
  value: string | null;
  onChange: (value: string | null) => void;
}) {
  // Nothing to choose from is not a filter. A visibly empty Category select
  // reads as "this business has no categories".
  if (options.length === 0) return null;

  return (
    <label className="flex flex-col gap-1 text-sm font-medium text-fg-default">
      {label}
      <select
        value={value ?? ""}
        onChange={(event) => onChange(event.target.value === "" ? null : event.target.value)}
        className="min-h-11 rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
      >
        <option value="">{anyLabel}</option>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.name}
          </option>
        ))}
      </select>
    </label>
  );
}
