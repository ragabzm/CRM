import { afterEach, describe, expect, it, vi } from "vitest";

import { DataTable } from "@/components/domain/DataTable/DataTable";
import { render, screen, within } from "@/__tests__/helpers/intl";

import { AUDIT_COLUMNS, COLUMNS, ROWS, getRowId } from "./fixtures";

afterEach(() => {
  vi.restoreAllMocks();
});

/**
 * The two collapse mechanisms.
 *
 * jsdom performs no layout, so these assert the STRUCTURE that makes each
 * mechanism work — which class carries the band, whether the folded value is
 * still in the DOM, whether the scroll region is reachable. The visual result is
 * a manual check at the three widths; what a test can guarantee is that no value
 * was dropped and no affordance is unreachable.
 */
describe("fold mode — scanned lists", () => {
  it("is the default, because most surfaces are scanned lists", () => {
    const { container } = render(
      <DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />,
    );

    expect(container.querySelector("[data-collapse-mode]")).toHaveAttribute(
      "data-collapse-mode",
      "fold",
    );
  });

  it("shows secondary columns only at the desktop band", () => {
    render(<DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />);

    const secondary = screen.getByRole("columnheader", { name: /age/i });

    // Hidden below desktop; the band alias is the only breakpoint referenced.
    expect(secondary.className).toContain("hidden");
    expect(secondary.className).toContain("desktop:table-cell");
    expect(secondary.className).not.toMatch(/(^|\s)(sm|md|lg):/);
  });

  it("moves every folded value into the row meta line — never drops it", () => {
    const { container } = render(
      <DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />,
    );

    const metas = container.querySelectorAll("[data-slot='row-meta']");
    expect(metas).toHaveLength(ROWS.length);

    // Every row's folded "age" value is present in its meta line.
    ROWS.forEach((row, index) => {
      const meta = metas[index]!;
      expect(within(meta as HTMLElement).getByText(String(row.age))).toBeInTheDocument();
    });
  });

  it("labels each folded value, so the meta line is not a row of bare numbers", () => {
    const { container } = render(
      <DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />,
    );

    const meta = container.querySelector("[data-slot='row-meta']")!;

    expect(meta.tagName).toBe("DL");
    expect(within(meta as HTMLElement).getByText("Age")).toBeInTheDocument();
  });

  it("hides the meta line at desktop, where the real columns are visible", () => {
    const { container } = render(
      <DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />,
    );

    expect(container.querySelector("[data-slot='row-meta']")!.className).toContain(
      "desktop:hidden",
    );
  });

  it("renders no meta line when nothing folds", () => {
    // Explicitly not foldable, rather than destructured-and-discarded.
    const noSecondary = COLUMNS.map((column) => ({ ...column, secondary: false }));

    const { container } = render(
      <DataTable caption="Tickets" columns={noSecondary} rows={ROWS} getRowId={getRowId} />,
    );

    expect(container.querySelector("[data-slot='row-meta']")).toBeNull();
  });

  it("does not scroll horizontally — the rows never move sideways", () => {
    const { container } = render(
      <DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />,
    );

    const wrapper = container.querySelector("[data-collapse-mode='fold']")!;
    expect(wrapper.className).not.toContain("overflow-x-auto");
    expect(wrapper).not.toHaveAttribute("role", "region");
  });

  it("warns when a pinned column is declared, because the mechanisms are exclusive", () => {
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});

    render(<DataTable caption="Tickets" columns={AUDIT_COLUMNS} rows={ROWS} getRowId={getRowId} />);

    expect(warn.mock.calls.flat().join(" ")).toMatch(/pinned.*ignored/i);
  });
});

describe("scroll mode — comparative tables", () => {
  function renderScroll() {
    return render(
      <DataTable
        caption="Audit log"
        mode="scroll"
        columns={AUDIT_COLUMNS}
        rows={ROWS}
        getRowId={getRowId}
      />,
    );
  }

  it("scrolls inside its own container", () => {
    const { container } = renderScroll();
    const wrapper = container.querySelector("[data-collapse-mode='scroll']")!;

    expect(wrapper.className).toContain("overflow-x-auto");
    // Keeps an inner scroll from chaining to the page behind it.
    expect(wrapper.className).toContain("overscroll-x-contain");
  });

  it("exposes the scroll area as a labelled, focusable region", () => {
    renderScroll();

    // A scrollable area that cannot be reached by keyboard or announced by a
    // screen reader is unusable, however good it looks.
    const region = screen.getByRole("region", { name: "Audit log" });
    expect(region).toHaveAttribute("tabindex", "0");
  });

  it("pins the identity column to the inline-start edge", () => {
    renderScroll();

    const identity = screen.getByRole("columnheader", { name: /reference/i });

    expect(identity).toHaveAttribute("data-pinned", "true");
    expect(identity.className).toContain("sticky");
    // Logical, not `left-0`: the pin follows the reading edge in Arabic too.
    expect(identity.className).toContain("start-0");
    expect(identity.className).not.toContain("left-0");
  });

  it("pins the identity cell in every row, not just the header", () => {
    const { container } = renderScroll();

    const pinnedCells = container.querySelectorAll("td[data-pinned='true']");
    expect(pinnedCells).toHaveLength(ROWS.length);
    pinnedCells.forEach((cell) => expect(cell.className).toContain("sticky"));
  });

  it("gives the pinned column an opaque background, so rows do not show through", () => {
    renderScroll();

    expect(screen.getByRole("columnheader", { name: /reference/i }).className).toContain(
      "bg-surface-raised",
    );
  });

  it("keeps every column — a comparative table folds nothing", () => {
    const { container } = renderScroll();

    expect(container.querySelector("[data-slot='row-meta']")).toBeNull();
    expect(screen.getAllByRole("columnheader")).toHaveLength(AUDIT_COLUMNS.length);
  });

  it("errors in development when no column is pinned", () => {
    const error = vi.spyOn(console, "error").mockImplementation(() => {});
    const unpinned = AUDIT_COLUMNS.map((column) => ({ ...column, pinned: false }));

    render(
      <DataTable
        caption="Audit log"
        mode="scroll"
        columns={unpinned}
        rows={ROWS}
        getRowId={getRowId}
      />,
    );

    // Without the pin, a horizontal scroll loses the reader entirely.
    expect(error.mock.calls.flat().join(" ")).toMatch(/pinned/i);
  });
});

describe("no silent truncation, in either mode", () => {
  it.each([
    ["fold", COLUMNS, undefined],
    ["scroll", AUDIT_COLUMNS, "scroll" as const],
  ])("%s renders every value somewhere in the DOM", (_name, columns, mode) => {
    const { container } = render(
      <DataTable
        caption="Table"
        {...(mode ? { mode } : {})}
        columns={columns}
        rows={ROWS}
        getRowId={getRowId}
      />,
    );

    for (const row of ROWS) {
      for (const value of [row.reference, row.subject, String(row.age)]) {
        expect(container.textContent).toContain(value);
      }
    }
  });

  it("never ellipses a cell — a clipped value is a dropped value", () => {
    const { container } = render(
      <DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />,
    );

    for (const cell of container.querySelectorAll("td")) {
      expect(cell.className).not.toContain("truncate");
      expect(cell.className).not.toContain("text-ellipsis");
    }
  });
});
