"use client";

import * as React from "react";

export interface RovingFocusOptions {
  rowCount: number;
  columnCount: number;
}

export interface RovingCell {
  row: number;
  column: number;
}

/**
 * Roving tabindex across a grid of cells.
 *
 * Exactly one cell is tabbable at a time, so Tab moves *past* the table rather
 * than through every cell in it — a 40x8 table would otherwise be 320 stops.
 * Arrow keys move within.
 *
 * Direction: ArrowLeft/ArrowRight are mapped through the document's writing
 * direction, so in RTL "left" moves to the next column rather than the previous
 * one. The keyboard should follow what the reader sees, not the DOM order.
 */
export function useRovingFocus({ rowCount, columnCount }: RovingFocusOptions) {
  const [stored, setActive] = React.useState<RovingCell>({ row: 0, column: 0 });
  const containerRef = React.useRef<HTMLTableElement | null>(null);

  /*
   * Clamped on read rather than corrected in an effect. When the grid shrinks
   * under us — a filter applied, a short last page — an effect that called
   * setState would render once with an out-of-range cell and then again with
   * the corrected one. Deriving it means there is never a frame pointing at a
   * row that does not exist.
   */
  const active: RovingCell = React.useMemo(
    () => ({
      row: Math.min(stored.row, Math.max(rowCount - 1, 0)),
      column: Math.min(stored.column, Math.max(columnCount - 1, 0)),
    }),
    [stored, rowCount, columnCount],
  );

  const focusCell = React.useCallback((cell: RovingCell) => {
    const container = containerRef.current;
    if (!container) return;

    const selector = `[data-row-index="${cell.row}"][data-column-index="${cell.column}"]`;
    const target = container.querySelector<HTMLElement>(selector);
    target?.focus();
  }, []);

  const isRtl = React.useCallback(() => {
    const container = containerRef.current;
    if (!container || typeof window === "undefined") return false;

    return window.getComputedStyle(container).direction === "rtl";
  }, []);

  const onKeyDown = React.useCallback(
    (event: React.KeyboardEvent<HTMLTableElement>) => {
      const { key } = event;
      if (
        key !== "ArrowUp" &&
        key !== "ArrowDown" &&
        key !== "ArrowLeft" &&
        key !== "ArrowRight" &&
        key !== "Home" &&
        key !== "End"
      ) {
        return;
      }

      event.preventDefault();

      setActive((current) => {
        let { row, column } = current;

        // In RTL the inline axis is mirrored: ArrowLeft advances.
        const inlineForward = isRtl() ? "ArrowLeft" : "ArrowRight";
        const inlineBack = isRtl() ? "ArrowRight" : "ArrowLeft";

        if (key === "ArrowDown") row = Math.min(row + 1, rowCount - 1);
        else if (key === "ArrowUp") row = Math.max(row - 1, 0);
        else if (key === inlineForward) column = Math.min(column + 1, columnCount - 1);
        else if (key === inlineBack) column = Math.max(column - 1, 0);
        else if (key === "Home") column = 0;
        else if (key === "End") column = columnCount - 1;

        const next = { row, column };
        // Focus after commit so the DOM has the new tabindex.
        queueMicrotask(() => focusCell(next));
        return next;
      });
    },
    [rowCount, columnCount, focusCell, isRtl],
  );

  /** Props for a cell: exactly one cell in the grid is tabbable. */
  const cellProps = React.useCallback(
    (row: number, column: number) => ({
      "data-row-index": row,
      "data-column-index": column,
      tabIndex: active.row === row && active.column === column ? 0 : -1,
      onFocus: () => setActive({ row, column }),
    }),
    [active],
  );

  return { active, setActive, containerRef, onKeyDown, cellProps, focusCell };
}
