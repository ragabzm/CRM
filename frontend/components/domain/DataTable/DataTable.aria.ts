import type { SortDirection, SortState } from "./DataTable.types";

/**
 * The `aria-sort` value for a header cell.
 *
 * Only the column that is actually sorted reports a direction; every other
 * sortable header reports "none". A table where several headers claim to be
 * sorted is worse than one that announces nothing.
 */
export function ariaSortFor(columnId: string, sort: SortState): "ascending" | "descending" | "none" {
  if (!sort || sort.column !== columnId) {
    return "none";
  }

  return sort.direction === "asc" ? "ascending" : "descending";
}

/**
 * The next sort state when a header is activated.
 *
 * Cycle: none -> ascending -> descending -> none. The third click clears the
 * sort rather than looping back to ascending, so there is always a way back to
 * the server's default ordering without reloading.
 */
export function nextSortState(columnId: string, current: SortState): SortState {
  if (!current || current.column !== columnId) {
    return { column: columnId, direction: "asc" };
  }

  if (current.direction === "asc") {
    return { column: columnId, direction: "desc" };
  }

  return null;
}

/** Screen-reader announcement for the current sort. */
export function sortAnnouncement(columnHeader: string, direction: SortDirection | null): string {
  if (direction === null) {
    return `${columnHeader}, not sorted`;
  }

  return direction === "asc"
    ? `${columnHeader}, sorted ascending`
    : `${columnHeader}, sorted descending`;
}
