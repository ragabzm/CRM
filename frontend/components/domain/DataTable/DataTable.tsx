"use client";

import { ArrowDown, ArrowUp, ChevronsUpDown, Search, SlidersHorizontal, X } from "lucide-react";
import { useTranslations } from "next-intl";
import * as React from "react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";

import { ariaSortFor, nextSortState, sortAnnouncement } from "./DataTable.aria";
import type { ActiveFilters, ColumnDef, DataTableProps } from "./DataTable.types";
import { useRetainFocusAcrossPages } from "./useRetainFocusAcrossPages";
import { useRovingFocus } from "./useRovingFocus";

/** Stable target for aria-describedby on the locked toggle. */
const IDENTITY_LOCK_REASON_ID = "identity-column-lock-reason";

function SortGlyph({ state }: { state: "ascending" | "descending" | "none" }) {
  // A glyph, not just a colour: the sort direction has to survive greyscale.
  if (state === "ascending") return <ArrowUp aria-hidden="true" className="size-3.5" />;
  if (state === "descending") return <ArrowDown aria-hidden="true" className="size-3.5" />;
  return <ChevronsUpDown aria-hidden="true" className="size-3.5 text-fg-subtle" />;
}

/**
 * Layer-B data table.
 *
 * Real `<table>` semantics on purpose: a grid of divs loses the row/column
 * relationships a screen reader needs, and no amount of ARIA puts them back
 * reliably.
 */
export function DataTable<Row>({
  columns,
  rows,
  getRowId,
  caption,
  mode = "fold",
  sort = null,
  onSortChange,
  filters = [],
  activeFilters = {},
  onFiltersChange,
  search = "",
  onSearchChange,
  hiddenColumns = [],
  onHiddenColumnsChange,
  page = 1,
  pageCount = 1,
  onPageChange,
  emptyState,
}: DataTableProps<Row>) {
  const t = useTranslations("dataTable");
  const tCommon = useTranslations("common");
  const [announcement, setAnnouncement] = React.useState("");

  const visibleColumns = React.useMemo(
    () => columns.filter((column) => column.identity || !hiddenColumns.includes(column.id)),
    [columns, hiddenColumns],
  );

  /** Columns that fold away below the desktop band, in declaration order. */
  const foldedColumns = React.useMemo(
    () => (mode === "fold" ? visibleColumns.filter((column) => column.secondary) : []),
    [mode, visibleColumns],
  );

  /*
   * Dev-time misuse guards. These are contract violations that produce a table
   * which looks fine in the reviewer's browser and fails at a band nobody
   * opened, so they are surfaced loudly during development rather than left to
   * be discovered on a phone.
   */
  React.useEffect(() => {
    if (process.env.NODE_ENV === "production") return;

    const pinned = columns.filter((column) => column.pinned);

    if (mode === "scroll" && pinned.length === 0) {
      console.error(
        '[DataTable] mode="scroll"without a pinned column. The pinned identity ' +
          "column is what stops the horizontal scroll from losing the reader — " +
          "mark one column with `pinned: true`.",
      );
    }

    if (mode === "fold" && pinned.length > 0) {
      console.warn(
        '[DataTable] `pinned` is ignored in mode="fold"; fold and scroll are ' +
          'exclusive mechanisms. Remove `pinned` or switch to mode="scroll".',
      );
    }

    if (mode === "scroll" && columns.some((column) => column.secondary)) {
      console.warn(
        '[DataTable] `secondary` is ignored in mode="scroll"; a comparative ' +
          "table keeps every column and scrolls instead of folding.",
      );
    }
  }, [mode, columns]);

  const { active, containerRef, onKeyDown, cellProps, focusCell } = useRovingFocus({
    rowCount: rows.length,
    columnCount: visibleColumns.length,
  });

  const restore = React.useCallback(
    (rowIndex: number) => focusCell({ row: rowIndex, column: active.column }),
    [focusCell, active.column],
  );

  useRetainFocusAcrossPages({ page, rowCount: rows.length, activeRow: active.row, restore });

  function handleSort(column: ColumnDef<Row>) {
    if (!column.sortable) return;

    const next = nextSortState(column.id, sort);
    onSortChange?.(next);
    setAnnouncement(
      sortAnnouncement(column.header, next && next.column === column.id ? next.direction : null),
    );
  }

  function toggleColumn(column: ColumnDef<Row>) {
    if (column.identity) return; // locked; the control is disabled anyway

    const hidden = hiddenColumns.includes(column.id)
      ? hiddenColumns.filter((id) => id !== column.id)
      : [...hiddenColumns, column.id];

    onHiddenColumnsChange?.(hidden);
  }

  function setFilter(filterId: string, value: string) {
    const next: ActiveFilters = { ...activeFilters };
    if (value === "") {
      delete next[filterId];
    } else {
      next[filterId] = value;
    }
    onFiltersChange?.(next);
  }

  const activeFilterEntries = Object.entries(activeFilters);

  return (
    <div className="flex flex-col gap-3">
      {/* ---------- toolbar: search + filters + column visibility ---------- */}
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-60 flex-1">
          <Search
            aria-hidden="true"
            className="pointer-events-none absolute top-1/2 size-4 -translate-y-1/2 text-fg-subtle start-2.5"
          />
          <Input
            type="search"
            value={search}
            aria-label={t("search")}
            placeholder={t("search")}
            className="ps-8"
            onChange={(event) => onSearchChange?.(event.target.value)}
          />
        </div>

        {filters.map((filter) => (
          <label key={filter.id} className="flex items-center gap-1.5 text-sm text-fg-muted">
            <span>{filter.label}</span>
            <select
              value={activeFilters[filter.id] ?? ""}
              onChange={(event) => setFilter(filter.id, event.target.value)}
              className="h-8 rounded-md border border-border-default bg-surface-raised px-2 text-sm text-fg-default"
            >
              <option value="">{tCommon("all")}</option>
              {filter.options.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>
        ))}

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="secondary" size="md" icon={<SlidersHorizontal aria-hidden="true" />}>
              {t("columns")}
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="min-w-52">
            {/*
 Its own provider: a Layer-B component must render correctly
 wherever it is dropped, not only inside a tree that happens to
 have installed one. Radix tolerates nesting, so the app-level
 provider in RootLayout still governs elsewhere.
            */}
            <TooltipProvider>
              <DropdownMenuLabel>{t("visibleColumns")}</DropdownMenuLabel>
              <DropdownMenuSeparator />
              {columns.map((column) => {
                const checked = column.identity || !hiddenColumns.includes(column.id);

                /*
                 * The identity column's toggle is RENDERED AND DISABLED, never
                 * omitted. "Locked, not absent": a missing control makes the
                 * reader hunt for it; a disabled one with a reason teaches the
                 * rule.
                 */
                const control = (
                  <div
                    key={column.id}
                    className={cn(
                      "flex items-center gap-2 rounded-md px-1.5 py-1 text-sm",
                      column.identity ? "opacity-70" : "hover:bg-surface-hover",
                    )}
                  >
                    <Checkbox
                      id={`column-toggle-${column.id}`}
                      checked={checked}
                      disabled={column.identity ?? false}
                      aria-disabled={column.identity ? true : undefined}
                      /*
                       * Points at the always-rendered sr-only description, NOT at
                       * the tooltip: tooltip content only exists in the DOM while
                       * it is open, so a keyboard user focusing the disabled
                       * toggle would otherwise be told nothing about why it is
                       * locked.
                       */
                      aria-describedby={column.identity ? IDENTITY_LOCK_REASON_ID : undefined}
                      onCheckedChange={() => toggleColumn(column)}
                    />
                    <label htmlFor={`column-toggle-${column.id}`} className="flex-1">
                      {column.header}
                    </label>
                  </div>
                );

                if (!column.identity) return control;

                return (
                  <Tooltip key={column.id}>
                    <TooltipTrigger asChild>{control}</TooltipTrigger>
                    <TooltipContent>{t("identityLocked")}</TooltipContent>
                  </Tooltip>
                );
              })}
              {/* Also readable by assistive tech without opening the tooltip. */}
              <span className="sr-only" id={IDENTITY_LOCK_REASON_ID}>
                {t("identityLocked")}
              </span>
            </TooltipProvider>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      {/* ---------- active filter chips ---------- */}
      {activeFilterEntries.length > 0 && (
        <ul className="flex flex-wrap items-center gap-1.5" aria-label={t("activeFilters")}>
          {activeFilterEntries.map(([filterId, value]) => {
            const filter = filters.find((candidate) => candidate.id === filterId);
            const option = filter?.options.find((candidate) => candidate.value === value);

            return (
              <li key={filterId}>
                <span className="inline-flex items-center gap-1 rounded-pill border border-border-default bg-surface-sunken py-0.5 text-xs text-fg-default ps-2 pe-1">
                  <span className="text-fg-muted">{filter?.label ?? filterId}:</span>
                  <span>{option?.label ?? value}</span>
                  <button
                    type="button"
                    aria-label={t("removeFilter", { label: filter?.label ?? filterId })}
                    className="rounded-full p-0.5 text-fg-muted hover:bg-surface-active hover:text-fg-default"
                    onClick={() => setFilter(filterId, "")}
                  >
                    <X aria-hidden="true" className="size-3" />
                  </button>
                </span>
              </li>
            );
          })}
        </ul>
      )}

      {/* ---------- the table ---------- */}
      <div
        /*
         * In scroll mode this is the scroll container, and it has to be a
         * labelled, focusable region: a scrollable area that cannot be reached
         * or announced is unusable by keyboard and invisible to a screen reader.
         * In fold mode nothing scrolls horizontally, so it is a plain box.
         */
        {...(mode === "scroll"
          ? { role: "region" as const, "aria-label": caption, tabIndex: 0 }
          : {})}
        data-collapse-mode={mode}
        className={cn(
          "rounded-md border border-border-default bg-surface-raised",
          mode === "scroll" ? "overflow-x-auto overscroll-x-contain" : "overflow-x-hidden",
        )}
      >
        <table ref={containerRef} onKeyDown={onKeyDown} className="w-full border-collapse text-sm">
          <caption className="sr-only">{caption}</caption>
          <thead>
            <tr className="border-b border-border-default">
              {visibleColumns.map((column) => {
                const sortState = ariaSortFor(column.id, sort);

                return (
                  <th
                    key={column.id}
                    scope="col"
                    aria-sort={column.sortable ? sortState : undefined}
                    data-column-id={column.id}
                    data-secondary={column.secondary ? "true" : undefined}
                    data-pinned={mode === "scroll" && column.pinned ? "true" : undefined}
                    className={cn(
                      "h-(--row-height) whitespace-nowrap px-3 text-start align-middle text-xs font-semibold text-fg-muted",
                      // Folds away below desktop; its value moves to the row's
                      // meta line, never disappears.
                      mode === "fold" && column.secondary && "hidden desktop:table-cell",
                      // Logical inset-inline-start, so the identity column pins
                      // to the reading edge in Arabic as well as English.
                      mode === "scroll" &&
                        column.pinned &&
                        "sticky start-0 z-20 bg-surface-raised shadow-[1px_0_0_var(--border-subtle)]",
                      column.type === "number" && "text-end",
                    )}
                  >
                    {column.sortable ? (
                      <button
                        type="button"
                        onClick={() => handleSort(column)}
                        className="inline-flex items-center gap-1.5 rounded-sm text-fg-muted hover:text-fg-default"
                      >
                        <span>{column.header}</span>
                        <SortGlyph state={sortState} />
                      </button>
                    ) : column.header === "" ? (
                      /*
                       * A column header is never allowed to be empty.
                       *
                       * The actions column has no visible heading — a word
                       * above a row of icon buttons is noise — but a screen
                       * reader announcing "column 9, blank" tells its reader
                       * nothing about what is in it. So the name is present and
                       * only visually hidden.
                       *
                       * Fixed here rather than at each call site because every
                       * table in this application has an actions column, and
                       * every one of them was affected.
                       */
                      <span className="sr-only">{t("rowActions")}</span>
                    ) : (
                      column.header
                    )}
                  </th>
                );
              })}
            </tr>
          </thead>

          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={visibleColumns.length} className="p-0">
                  {emptyState}
                </td>
              </tr>
            ) : (
              rows.map((row, rowIndex) => (
                <tr
                  key={getRowId(row)}
                  className="border-b border-border-subtle last:border-b-0 hover:bg-surface-hover"
                >
                  {visibleColumns.map((column, columnIndex) => (
                    <td
                      key={column.id}
                      data-column-type={column.type ?? "text"}
                      data-pinned={mode === "scroll" && column.pinned ? "true" : undefined}
                      className={cn(
                        "h-(--row-height) px-3 align-middle text-fg-default",
                        mode === "fold" && column.secondary && "hidden desktop:table-cell",
                        mode === "scroll" &&
                          column.pinned &&
                          "sticky start-0 z-10 bg-surface-raised shadow-[1px_0_0_var(--border-subtle)]",
                        column.type === "number" && "text-end",
                      )}
                      {...cellProps(rowIndex, columnIndex)}
                    >
                      {column.cell(row)}

                      {/*
 The fold's other half. Every column hidden above is
 reprinted here, labelled, below the desktop band — the
 value is MOVED, never dropped. It rides in the first
 visible cell so the row stays one <tr> and the table
 keeps real row/column semantics.
                      */}
                      {columnIndex === 0 && foldedColumns.length > 0 && (
                        <dl
                          data-slot="row-meta"
                          className="flex flex-wrap gap-x-3 gap-y-0.5 pt-1 text-xs text-fg-muted desktop:hidden"
                        >
                          {foldedColumns.map((folded) => (
                            <div key={folded.id} className="flex items-center gap-1">
                              <dt className="sr-only">{folded.header}</dt>
                              <dd
                                data-column-id={folded.id}
                                data-column-type={folded.type ?? "text"}
                              >
                                {folded.cell(row)}
                              </dd>
                            </div>
                          ))}
                        </dl>
                      )}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* ---------- pagination ---------- */}
      {pageCount > 1 && (
        <div className="flex items-center justify-between gap-2">
          <p className="text-sm text-fg-muted">
            {t.rich("pageOf", {
              page,
              pageCount,
              // Counts are formatted numbers on a numeric surface, not prose.
              num: (chunks) => <span data-numeric="true">{chunks}</span>,
            })}
          </p>
          <div className="flex items-center gap-1.5">
            <Button
              variant="secondary"
              size="sm"
              disabled={page <= 1}
              onClick={() => onPageChange?.(page - 1)}
            >
              {t("previous")}
            </Button>
            <Button
              variant="secondary"
              size="sm"
              disabled={page >= pageCount}
              onClick={() => onPageChange?.(page + 1)}
            >
              {t("next")}
            </Button>
          </div>
        </div>
      )}

      {/* Sort changes are announced politely rather than moving focus. */}
      <output aria-live="polite" className="sr-only">
        {announcement}
      </output>
    </div>
  );
}
