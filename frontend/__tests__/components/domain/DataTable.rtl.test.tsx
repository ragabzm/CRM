import { render } from "@/__tests__/helpers/intl";
import { describe, expect, it } from "vitest";

import { DataTable } from "@/components/domain/DataTable/DataTable";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { RowActions } from "@/components/domain/RowActions/RowActions";

import { AUDIT_COLUMNS, COLUMNS, FILTERS, ROWS, getRowId } from "./fixtures";

/** Physical CSS that would break when the document direction flips. */
const PHYSICAL_CSS = /(?:^|[\s;"{])(?:left|right|margin-left|margin-right|padding-left|padding-right)\s*:/;

/** Physical Tailwind utilities, as whole class tokens. */
const PHYSICAL_UTILITY = /(?:^|\s)(?:[\w[\]&>:._-]+:)*(?:ml|mr|pl|pr)-[\w./-]+(?=\s|"|$)/;

function renderInRtl(node: React.ReactElement) {
  const wrapper = document.createElement("div");
  wrapper.setAttribute("dir", "rtl");
  document.body.appendChild(wrapper);
  return render(node, { container: wrapper });
}

describe("domain components under dir=rtl", () => {
  it("DataTable leaks no physical-direction CSS", () => {
    const { container } = renderInRtl(
      <DataTable
        caption="Tickets"
        columns={COLUMNS}
        rows={ROWS}
        getRowId={getRowId}
        filters={FILTERS}
        activeFilters={{ priority: "urgent" }}
        page={1}
        pageCount={3}
      />,
    );

    expect(container.innerHTML).not.toMatch(PHYSICAL_CSS);
    expect(container.innerHTML).not.toMatch(PHYSICAL_UTILITY);
  });

  it.each([
    ["EmptyState", <EmptyState key="e" headline="No data" description="Nothing matched" count="0 of 1,284" />],
    [
      "ForbiddenState",
      <ForbiddenState key="f" headline="You do not have access" description="Outside your scope" withheldLabel="tickets" />,
    ],
    ["RowActions", <RowActions key="r" rowLabel="TKT-000123" actions={[{ id: "a", label: "Open", onSelect: () => {} }]} />],
  ])("%s leaks no physical-direction CSS", (_name, node) => {
    const { container } = renderInRtl(node);

    expect(container.innerHTML).not.toMatch(PHYSICAL_CSS);
    expect(container.innerHTML).not.toMatch(PHYSICAL_UTILITY);
  });

  it("flipping dir changes nothing in the DOM but the attribute", () => {
    const table = (
      <DataTable caption="Tickets" columns={COLUMNS} rows={ROWS} getRowId={getRowId} />
    );

    // Radix mints an incrementing id per mount; that counter is not a layout
    // difference, so it is normalised out before comparing.
    const normalise = (html: string, dir: string) =>
      html.replace(`dir="${dir}"`, "").replace(/radix-[_\w]+/g, "radix-id");

    const ltr = render(<div dir="ltr">{table}</div>);
    const ltrHtml = normalise(ltr.container.innerHTML, "ltr");
    ltr.unmount();

    const rtl = render(<div dir="rtl">{table}</div>);
    const rtlHtml = normalise(rtl.container.innerHTML, "rtl");

    // Identical markup in both directions: the layout is carried entirely by
    // logical CSS, so a runtime locale flip needs no re-render logic.
    expect(rtlHtml).toBe(ltrHtml);
  });
});

describe("the pinned identity column under dir=rtl", () => {
  it("pins to the inline-start edge, which is the RIGHT in Arabic", () => {
    const { container } = renderInRtl(
      <DataTable
        caption="Audit log"
        mode="scroll"
        columns={AUDIT_COLUMNS}
        rows={ROWS}
        getRowId={getRowId}
      />,
    );

    const pinned = container.querySelectorAll("[data-pinned='true']");
    expect(pinned.length).toBeGreaterThan(0);

    for (const cell of pinned) {
      // `start-0` resolves to right:0 under rtl; `left-0` would strand the
      // identity column on the far side of the scroll and defeat the pin.
      expect(cell.className).toContain("start-0");
      expect(cell.className).not.toMatch(/(^|\s)(left|right)-0(\s|$)/);
    }
  });

  it("leaks no physical-direction CSS in scroll mode", () => {
    const { container } = renderInRtl(
      <DataTable
        caption="Audit log"
        mode="scroll"
        columns={AUDIT_COLUMNS}
        rows={ROWS}
        getRowId={getRowId}
      />,
    );

    expect(container.innerHTML).not.toMatch(PHYSICAL_CSS);
    expect(container.innerHTML).not.toMatch(PHYSICAL_UTILITY);
  });

  it("renders identical markup in both directions in scroll mode", () => {
    const table = (
      <DataTable
        caption="Audit log"
        mode="scroll"
        columns={AUDIT_COLUMNS}
        rows={ROWS}
        getRowId={getRowId}
      />
    );

    const normalise = (html: string, dir: string) =>
      html.replace(`dir="${dir}"`, "").replace(/radix-[_\w]+/g, "radix-id");

    const ltr = render(<div dir="ltr">{table}</div>);
    const ltrHtml = normalise(ltr.container.innerHTML, "ltr");
    ltr.unmount();

    const rtl = render(<div dir="rtl">{table}</div>);
    expect(normalise(rtl.container.innerHTML, "rtl")).toBe(ltrHtml);
  });
});
