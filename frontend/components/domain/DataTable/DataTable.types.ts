import type * as React from "react";

/** Sort direction as the API expresses it. */
export type SortDirection = "asc" | "desc";

/** Server-driven sort state. `null` means "no column is sorted". */
export type SortState = {
  column: string;
  direction: SortDirection;
} | null;

/**
 * How a column's values behave, which decides alignment and figure treatment.
 * `number` columns get tabular figures and bidi isolation via
 * `td[data-column-type="number"]` in globals.css.
 */
export type ColumnType = "text" | "number" | "date" | "status" | "action";

export interface ColumnDef<Row> {
  /** Stable key. Also the value sent to the server when sorting. */
  id: string;
  /** Visible column heading. */
  header: string;
  /** Cell renderer. */
  cell: (row: Row) => React.ReactNode;
  type?: ColumnType;
  sortable?: boolean;
  /**
   * The identity column — the one that says *which* record a row is.
   *
   * It is LOCKED VISIBLE, not absent from the visibility menu: a table whose
   * rows cannot be identified is not a shorter table, it is a broken one. The
   * toggle still renders, disabled, with an accessible explanation, so the
   * reader learns the rule rather than wondering where the control went.
   */
  identity?: boolean;
  /**
   * Secondary columns collapse into a per-row expander below the breakpoint
   * instead of forcing a horizontal scroll.
   */
  secondary?: boolean;
}

export interface FilterOption {
  value: string;
  label: string;
}

export interface FilterDef {
  id: string;
  label: string;
  options: FilterOption[];
}

/** Active filters as `{ [filterId]: selectedValue }`. */
export type ActiveFilters = Record<string, string>;

export interface DataTableProps<Row> {
  columns: ColumnDef<Row>[];
  rows: Row[];
  /** Stable identity for a row, used for keys and focus retention. */
  getRowId: (row: Row) => string;

  caption: string;

  /* --- server-driven sorting --- */
  sort?: SortState;
  onSortChange?: (sort: SortState) => void;

  /* --- filtering --- */
  filters?: FilterDef[];
  activeFilters?: ActiveFilters;
  onFiltersChange?: (filters: ActiveFilters) => void;

  /* --- text search --- */
  search?: string;
  onSearchChange?: (search: string) => void;

  /* --- column visibility --- */
  hiddenColumns?: string[];
  onHiddenColumnsChange?: (hidden: string[]) => void;

  /* --- pagination --- */
  page?: number;
  pageCount?: number;
  onPageChange?: (page: number) => void;

  /** Rendered in place of the table body when there are no rows. */
  emptyState?: React.ReactNode;
}
