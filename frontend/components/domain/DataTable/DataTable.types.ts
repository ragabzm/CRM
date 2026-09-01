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
   * Marks a column as foldable: below the desktop band its cell is hidden and
   * its value moves into the row's meta line.
   *
   * A column WITHOUT this flag never folds at any band. That is how the design
   * expresses "the SLA never folds" — it is the reason the list is sorted the
   * way it is, so it holds its own column even where Priority and Assignee lose
   * theirs.
   */
  secondary?: boolean;

  /**
   * For collapse="scroll" tables: the identity column that stays pinned to the
   * inline-start edge while the rest of the table scrolls under it.
   *
   * Ignored in fold mode — the two mechanisms are exclusive, and mixing them is
   * a dev-time warning.
   */
  pinned?: boolean;
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

/**
 * How the table gives up horizontal room.
 *
 * The choice is about what the reader is doing, not about how many columns
 * there are:
 *
 *   "fold"   — the row is an object you are going to OPEN. Ticket list, customer
 *              list, article list. The reader is scanning for the next one, not
 *              reading down a column comparing values, so hiding a column and
 *              moving its value into the row costs nothing and keeps twelve
 *              scannable rows on screen.
 *
 *   "scroll" — the columns are a series you are going to COMPARE. Reports, the
 *              SLA compliance table, the audit log. Re-flowing a twelve-column
 *              report into twelve stacked label/value pairs destroys the only
 *              thing a report is for; the pinned identity column is what stops
 *              the scroll from losing you.
 *
 * Neither mode ever drops a value. See board R-1 of the mockup at
 * .squad/stories/inti/495/attachments/screen-responsive.html.
 */
export type CollapseMode = "fold" | "scroll";

export interface DataTableProps<Row> {
  columns: ColumnDef<Row>[];

  /**
   * Defaults to "fold", which is the scanned-list behaviour every list surface
   * wants. Choose "scroll" only for a comparative table.
   */
  mode?: CollapseMode;
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
