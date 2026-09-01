import { render } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";

import { DataTable } from "@/components/domain/DataTable/DataTable";

import { COLUMNS, ROWS, getRowId } from "./fixtures";

function cellAt(row: number, column: number) {
  return document.querySelector<HTMLElement>(
    `[data-row-index="${row}"][data-column-index="${column}"]`,
  );
}

describe("DataTable roving tabindex", () => {
  it("makes exactly one cell tabbable", () => {
    render(<DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />);

    const tabbable = document
      .querySelectorAll("td[data-row-index]")
      .values()
      .filter((cell) => cell.getAttribute("tabindex") === "0");

    expect([...tabbable]).toHaveLength(1);
  });

  it("moves down and up rows with the arrow keys", async () => {
    const user = userEvent.setup();
    render(<DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />);

    cellAt(0, 0)!.focus();
    await user.keyboard("{ArrowDown}");
    expect(cellAt(1, 0)).toHaveFocus();

    await user.keyboard("{ArrowDown}");
    expect(cellAt(2, 0)).toHaveFocus();

    await user.keyboard("{ArrowUp}");
    expect(cellAt(1, 0)).toHaveFocus();
  });

  it("moves across columns with the arrow keys in LTR", async () => {
    const user = userEvent.setup();
    render(<DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />);

    cellAt(0, 0)!.focus();
    await user.keyboard("{ArrowRight}");
    expect(cellAt(0, 1)).toHaveFocus();

    await user.keyboard("{ArrowLeft}");
    expect(cellAt(0, 0)).toHaveFocus();
  });

  it("does not walk past the edges of the grid", async () => {
    const user = userEvent.setup();
    render(<DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />);

    cellAt(0, 0)!.focus();
    await user.keyboard("{ArrowUp}{ArrowLeft}");
    expect(cellAt(0, 0)).toHaveFocus();

    const lastRow = ROWS.length - 1;
    cellAt(lastRow, 0)!.focus();
    await user.keyboard("{ArrowDown}");
    expect(cellAt(lastRow, 0)).toHaveFocus();
  });

  it("jumps to the first and last column with Home and End", async () => {
    const user = userEvent.setup();
    render(<DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />);

    cellAt(0, 0)!.focus();
    await user.keyboard("{End}");
    expect(cellAt(0, COLUMNS.length - 1)).toHaveFocus();

    await user.keyboard("{Home}");
    expect(cellAt(0, 0)).toHaveFocus();
  });
});
