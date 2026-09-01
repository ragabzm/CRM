import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";

/** Western digits AND Arabic-Indic digits. Both are numerals to a reader. */
const ANY_NUMERAL = /[0-9\u0660-\u0669]/;

/**
 * The guard runs over RENDERED TEXT, not markup.
 *
 * innerHTML is the wrong surface: an SVG glyph's path data ("M 2 10 …") and
 * Tailwind class names ("size-10", "border-s-4") are full of digits that no
 * reader ever sees. Asserting on markup would either fail on every icon or
 * force the component to avoid icons entirely — neither of which is what UX-06
 * is protecting. What must not leak is a *printed* number.
 */
function visibleText(element: Element): string {
  return element.textContent ?? "";
}

/**
 * UX-06 leak guard.
 *
 * A permission surface that prints `0` has leaked that the count is small, and
 * has also told a lie — how many records the reader is not scoped to see is
 * itself information they are not scoped to see. Zero is an answer; the em dash
 * is a refusal to answer.
 */
describe("ForbiddenState prints no numeral", () => {
  it("renders no numeral at all in its default form", () => {
    const { container } = render(
      <ForbiddenState
        headline="You do not have access to this data"
        description="Deliveries is outside your scope. You supervise Billing in Riyadh."
        withheldLabel="tickets"
      />,
    );

    expect(visibleText(container)).not.toMatch(ANY_NUMERAL);
  });

  it("renders no numeral for any documented variant", () => {
    const variants = [
      <ForbiddenState key="a" headline="You do not have access to this data" />,
      <ForbiddenState key="b" headline="Restricted" withheldLabel="tickets" />,
      <ForbiddenState
        key="c"
        headline="Restricted"
        description="Outside your scope."
        withheldLabel="tickets"
        action={<button type="button">Request access</button>}
        secondaryAction={<button type="button">Return to Billing</button>}
      />,
    ];

    for (const variant of variants) {
      const { container, unmount } = render(variant);
      expect(visibleText(container)).not.toMatch(ANY_NUMERAL);
      unmount();
    }
  });

  it("rejects Arabic-Indic digits too", () => {
    const { container } = render(
      <ForbiddenState headline="لا تملك صلاحية الوصول" withheldLabel="تذاكر" />,
    );

    expect(visibleText(container)).not.toMatch(ANY_NUMERAL);
  });

  it("shows an em dash where a count would be", () => {
    render(<ForbiddenState headline="Restricted" withheldLabel="tickets" />);

    expect(screen.getByText("—")).toBeInTheDocument();
    expect(screen.getByText(/not disclosed/i)).toBeInTheDocument();
  });

  it("keeps the count region numeral-free even when a support reference is shown", () => {
    /*
     * Board R-6 requires a quotable reference code, and a code contains digits.
     * Those digits are a support handle, not a count — so they render outside
     * the guarded region, and the guarded region stays numeral-free.
     */
    const { container } = render(
      <ForbiddenState
        headline="You do not have access to this data"
        withheldLabel="tickets"
        reference="ERR-SCOPE-403 · ref 7K2-4801"
        auditNote="Recorded in the audit log. That is routine, not an accusation."
      />,
    );

    const guarded = container.querySelector('[data-region="forbidden-no-numeral"]');

    expect(guarded).not.toBeNull();
    expect(visibleText(guarded!)).not.toMatch(ANY_NUMERAL);
    // The reference itself is still shown, outside the guarded region.
    expect(screen.getByText(/ERR-SCOPE-403/)).toBeInTheDocument();
  });

  it("offers no way to pass a count", () => {
    // Compile-time guarantee, asserted here so the intent is visible in tests
    // too: adding a `count` prop would fail typecheck.
    const props = Object.keys(
      ForbiddenState({ headline: "x" }).props as Record<string, unknown>,
    );

    expect(props).not.toContain("count");
  });
});
