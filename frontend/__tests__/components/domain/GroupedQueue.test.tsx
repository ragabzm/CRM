import { describe, expect, it } from "vitest";

import { render, screen } from "@/__tests__/helpers/intl";
import { DataTable } from "@/components/domain/DataTable/DataTable";
import type { ColumnDef } from "@/components/domain/DataTable/DataTable.types";

/**
 * A grouped queue is ONE table.
 *
 * The first attempt rendered a `DataTable` per group. Each one brought its own
 * search box and its own column picker, repeated the header row, and let its
 * columns settle to a different width — so a queue split into "Breached" and
 * "Waiting on the customer" read as two unrelated tables rather than one list
 * ordered by why each ticket needs attention.
 *
 * The heading is a spanning row inside the table: a divider, not the start of
 * something new.
 */

interface Row {
  id: string;
  name: string;
}

const COLUMNS: ColumnDef<Row>[] = [
  { id: "name", header: "Name", identity: true, cell: (row) => row.name },
  { id: "id", header: "Id", cell: (row) => row.id },
];

const ROWS: Row[] = [
  { id: "a", name: "Alpha" },
  { id: "b", name: "Beta" },
  { id: "c", name: "Gamma" },
];

function renderGrouped() {
  return render(
    <DataTable
      caption="Queue"
      columns={COLUMNS}
      rows={ROWS}
      getRowId={(row) => row.id}
      groups={[
        { id: "urgent", label: "Breached", note: "act now", rowIds: ["c", "a"] },
        { id: "rest", label: "Everything else", rowIds: ["b"] },
      ]}
    />,
  );
}

describe("a grouped table", () => {
  it("draws one header row for the whole queue", () => {
    renderGrouped();

    // Two groups used to mean two <thead>s and two sets of column widths.
    expect(document.querySelectorAll("thead")).toHaveLength(1);
  });

  it("draws one search box and one column picker", () => {
    renderGrouped();

    // Searching inside "Breached" alone, separately from "Everything else",
    // is not a thing anybody wants.
    expect(screen.getAllByRole("searchbox")).toHaveLength(1);
  });

  it("labels each group with a spanning heading", () => {
    renderGrouped();

    const headings = [...document.querySelectorAll('[data-slot="group-heading"]')];

    expect(headings).toHaveLength(2);
    expect(headings[0]).toHaveTextContent("Breached");
    expect(headings[0]).toHaveTextContent("act now");
    expect(headings[0]?.querySelector("th")).toHaveAttribute("colspan", "2");
  });

  it("puts the rows in the order the groups declare, not the order they arrived", () => {
    renderGrouped();

    const names = [...document.querySelectorAll("tbody tr td:first-child")].map(
      (cell) => cell.textContent,
    );

    // Gamma before Alpha, because the group says so.
    expect(names).toEqual(["Gamma", "Alpha", "Beta"]);
  });

  it("still renders a plain table when no groups are given", () => {
    render(<DataTable caption="Queue" columns={COLUMNS} rows={ROWS} getRowId={(row) => row.id} />);

    expect(document.querySelectorAll('[data-slot="group-heading"]')).toHaveLength(0);
    expect(document.querySelectorAll("tbody tr")).toHaveLength(3);
  });
});
