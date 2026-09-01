import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import * as React from "react";
import { describe, expect, it } from "vitest";

import { DataTable } from "@/components/domain/DataTable/DataTable";

import { COLUMNS, ROWS, getRowId, type TicketRow } from "./fixtures";

function cellAt(row: number, column: number) {
  return document.querySelector<HTMLElement>(
    `[data-row-index="${row}"][data-column-index="${column}"]`,
  );
}

/** Page 2 is deliberately short, to exercise the clamp. */
function pageRows(page: number): TicketRow[] {
  return page === 1 ? ROWS : ROWS.slice(0, 2);
}

function PagingHarness() {
  const [page, setPage] = React.useState(1);

  return (
    <DataTable
      caption="Tickets"
      columns={COLUMNS}
      rows={pageRows(page)}
      getRowId={getRowId}
      page={page}
      pageCount={2}
      onPageChange={setPage}
    />
  );
}

describe("DataTable focus retention across pagination", () => {
  it("returns focus to the same row index on the next page", async () => {
    const user = userEvent.setup();
    render(<PagingHarness />);

    // Focus row 1 (which exists on both pages).
    cellAt(1, 0)!.focus();
    expect(cellAt(1, 0)).toHaveFocus();

    await user.click(screen.getByRole("button", { name: /next/i }));

    expect(cellAt(1, 0)).toHaveFocus();
  });

  it("clamps to the last row when the new page is shorter, instead of losing focus", async () => {
    const user = userEvent.setup();
    render(<PagingHarness />);

    // Row 3 exists on page 1 but not on the 2-row page 2.
    cellAt(3, 0)!.focus();
    expect(cellAt(3, 0)).toHaveFocus();

    await user.click(screen.getByRole("button", { name: /next/i }));

    // Focus must land on the last available row, never on <body>.
    expect(cellAt(1, 0)).toHaveFocus();
    expect(document.body).not.toHaveFocus();
  });
});
