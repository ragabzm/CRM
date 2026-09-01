import { render } from "@/__tests__/helpers/intl";
import { describe, expect, it } from "vitest";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";

/**
 * Board R-6: these two look alike in every reporting product and they mean
 * opposite things. The board lists nine differences and not one of them is
 * colour — these tests pin the ones that live in the components.
 */
describe("EmptyState and ForbiddenState are unmistakable", () => {
  function renderBoth() {
    const empty = render(
      <EmptyState headline="No data for this selection" count="0 of 1,284" description="Nothing matched." />,
    );
    const emptyRoot = empty.container.querySelector("[data-slot='empty-state']")!;

    const forbidden = render(
      <ForbiddenState headline="You do not have access to this data" withheldLabel="tickets" />,
    );
    const forbiddenRoot = forbidden.container.querySelector("[data-slot='forbidden-state']")!;

    return { emptyRoot, forbiddenRoot };
  }

  it("1 · Layout — centred versus edge-anchored with a leading rule", () => {
    const { emptyRoot, forbiddenRoot } = renderBoth();

    expect(emptyRoot.className).toMatch(/items-center/);
    expect(emptyRoot.className).toMatch(/text-center/);

    expect(forbiddenRoot.className).toMatch(/items-start/);
    expect(forbiddenRoot.className).toMatch(/text-start/);
    // The 4px solid rule down the leading edge.
    expect(forbiddenRoot.className).toMatch(/border-s-4/);
  });

  it("2 · Mark — outlined and light versus filled and dark", () => {
    const { emptyRoot, forbiddenRoot } = renderBoth();

    const emptyMark = emptyRoot.querySelector("div[aria-hidden='true']")!;
    const forbiddenMark = forbiddenRoot.querySelector("div[aria-hidden='true']")!;

    expect(emptyMark.className).toMatch(/border/);
    expect(emptyMark.className).toMatch(/bg-surface-sunken/);

    expect(forbiddenMark.className).toMatch(/bg-surface-inverse/);
    expect(forbiddenMark.className).not.toMatch(/border-border/);
  });

  it("2 · Mark — the two icons are different glyphs", () => {
    const { emptyRoot, forbiddenRoot } = renderBoth();

    const emptyIcon = emptyRoot.querySelector("svg")!.getAttribute("class");
    const forbiddenIcon = forbiddenRoot.querySelector("svg")!.getAttribute("class");

    expect(emptyIcon).not.toBe(forbiddenIcon);
    expect(forbiddenIcon).toMatch(/lock/);
  });

  it("3 · The number — a printed count versus an em dash", () => {
    const { emptyRoot, forbiddenRoot } = renderBoth();

    expect(emptyRoot.textContent).toMatch(/0 of 1,284/);
    expect(forbiddenRoot.textContent).toMatch(/—/);
    expect(forbiddenRoot.textContent).toMatch(/not disclosed/);
    expect(forbiddenRoot.textContent).not.toMatch(/[0-9]/);
  });

  it("4 · Heading — different accessible names for the region", () => {
    const { emptyRoot, forbiddenRoot } = renderBoth();

    expect(emptyRoot.getAttribute("aria-label")).toBe("No results");
    expect(forbiddenRoot.getAttribute("aria-label")).toBe("Access denied");
    expect(emptyRoot.getAttribute("aria-label")).not.toBe(forbiddenRoot.getAttribute("aria-label"));
  });

  it("distinguishes itself by a data attribute, not by colour", () => {
    const { emptyRoot, forbiddenRoot } = renderBoth();

    expect(emptyRoot.getAttribute("data-state-kind")).toBe("empty");
    expect(forbiddenRoot.getAttribute("data-state-kind")).toBe("forbidden");
  });

  it("survives greyscale: every difference asserted above is non-colour", () => {
    const { emptyRoot, forbiddenRoot } = renderBoth();

    // Layout, mark fill, glyph, numeral and label all differ without reference
    // to hue — so desaturating the page changes none of these assertions.
    const emptyShape = [
      /items-center/.test(emptyRoot.className),
      /border-s-4/.test(emptyRoot.className),
      emptyRoot.textContent?.match(/[0-9]/) !== null,
    ].join("|");

    const forbiddenShape = [
      /items-center/.test(forbiddenRoot.className),
      /border-s-4/.test(forbiddenRoot.className),
      forbiddenRoot.textContent?.match(/[0-9]/) !== null,
    ].join("|");

    expect(forbiddenShape).not.toBe(emptyShape);
  });
});
