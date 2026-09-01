"use client";

import * as React from "react";

export interface RetainFocusOptions {
  /** Current page number. A change is what triggers restoration. */
  page: number;
  /** Rows on the page that just rendered. */
  rowCount: number;
  /** The row index that currently holds focus. */
  activeRow: number;
  /** Put focus back on this row index. */
  restore: (rowIndex: number) => void;
}

/**
 * Keeps keyboard focus on the same row position when the page changes.
 *
 * Without this, paging with the keyboard drops focus to <body> and the reader
 * has to Tab back into the table for every page — which is the moment most
 * people abandon the keyboard and reach for the mouse.
 *
 * If the new page is shorter than the old one (a short last page), focus clamps
 * to the last available row rather than being lost.
 */
export function useRetainFocusAcrossPages({
  page,
  rowCount,
  activeRow,
  restore,
}: RetainFocusOptions) {
  // The row index to return to, captured before the page swaps.
  const rememberedRow = React.useRef(activeRow);
  const previousPage = React.useRef(page);

  React.useEffect(() => {
    rememberedRow.current = activeRow;
  }, [activeRow]);

  React.useEffect(() => {
    if (previousPage.current === page) {
      return;
    }

    previousPage.current = page;

    if (rowCount === 0) {
      return;
    }

    const target = Math.min(rememberedRow.current, rowCount - 1);
    restore(target);
  }, [page, rowCount, restore]);
}
