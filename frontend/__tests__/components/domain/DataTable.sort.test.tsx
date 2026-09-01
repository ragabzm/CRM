import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import * as React from "react";
import { describe, expect, it, vi } from "vitest";

import { DataTable } from "@/components/domain/DataTable/DataTable";
import type { SortState } from "@/components/domain/DataTable/DataTable.types";

import { COLUMNS, ROWS, getRowId } from "./fixtures";

function SortHarness({ onSortChange }: { onSortChange?: (s: SortState) => void }) {
  const [sort, setSort] = React.useState<SortState>(null);

  return (
    <DataTable
      caption="Tickets"
      columns={COLUMNS}
      rows={ROWS}
      getRowId={getRowId}
      sort={sort}
      onSortChange={(next) => {
        setSort(next);
        onSortChange?.(next);
      }}
    />
  );
}

describe("DataTable server-driven sorting", () => {
  it("renders real table semantics", () => {
    render(<SortHarness />);

    expect(screen.getByRole("table")).toBeInTheDocument();
    expect(screen.getAllByRole("columnheader")).toHaveLength(3);
    expect(screen.getAllByRole("row").length).toBeGreaterThan(1);
  });

  it("cycles aria-sort none -> ascending -> descending -> none", async () => {
    const user = userEvent.setup();
    render(<SortHarness />);

    const header = screen.getByRole("columnheader", { name: /subject/i });
    expect(header).toHaveAttribute("aria-sort", "none");

    await user.click(screen.getByRole("button", { name: /subject/i }));
    expect(header).toHaveAttribute("aria-sort", "ascending");

    await user.click(screen.getByRole("button", { name: /subject/i }));
    expect(header).toHaveAttribute("aria-sort", "descending");

    await user.click(screen.getByRole("button", { name: /subject/i }));
    expect(header).toHaveAttribute("aria-sort", "none");
  });

  it("emits the sort payload the server needs", async () => {
    const user = userEvent.setup();
    const onSortChange = vi.fn();
    render(<SortHarness onSortChange={onSortChange} />);

    await user.click(screen.getByRole("button", { name: /subject/i }));

    expect(onSortChange).toHaveBeenCalledWith({ column: "subject", direction: "asc" });
  });

  it("only ever reports one sorted column", async () => {
    const user = userEvent.setup();
    render(<SortHarness />);

    await user.click(screen.getByRole("button", { name: /subject/i }));
    await user.click(screen.getByRole("button", { name: /reference/i }));

    const sorted = screen
      .getAllByRole("columnheader")
      .filter((header) => header.getAttribute("aria-sort") !== "none");

    expect(sorted).toHaveLength(1);
    expect(sorted[0]).toHaveAccessibleName(/reference/i);
  });

  it("announces the sort politely", async () => {
    const user = userEvent.setup();
    const { container } = render(<SortHarness />);

    await user.click(screen.getByRole("button", { name: /subject/i }));

    const live = container.querySelector("output[aria-live='polite']");
    expect(live).toHaveTextContent(/subject, sorted ascending/i);
  });
});
