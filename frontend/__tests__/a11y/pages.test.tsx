import { describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/",
}));

import { DataTable } from "@/components/domain/DataTable/DataTable";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { HomeScreen } from "@/components/screens/home/HomeScreen";
import { AppShell } from "@/components/shell/AppShell";
import { FileInput } from "@/components/ui/file-input";
import { withIntl } from "@/__tests__/helpers/intl";
import type { Locale } from "@/lib/i18n/locale";
import { render } from "@testing-library/react";

import { AUDIT_COLUMNS, COLUMNS, FILTERS, ROWS, getRowId } from "../components/domain/fixtures";
import { axe, axePage } from "./axe";

/**
 * The accessibility merge gate.
 *
 * Every surface is checked in BOTH writing directions. A violation that only
 * appears in Arabic — an unlabelled control whose label came from a direction-
 * specific branch, a contrast failure in a mirrored layout — is exactly the kind
 * that ships, because most reviewers do not read the Arabic build.
 */
const DIRECTIONS: Array<{ dir: "ltr" | "rtl"; locale: Locale }> = [
  { dir: "ltr", locale: "en" },
  { dir: "rtl", locale: "ar" },
];

/** Renders into a real landmark, so page-level rules are meaningful. */
function renderPage(ui: React.ReactElement, dir: "ltr" | "rtl", locale: Locale) {
  const host = document.createElement("div");
  host.setAttribute("dir", dir);
  host.setAttribute("lang", locale);
  document.body.appendChild(host);

  return render(withIntl(<main>{ui}</main>, locale), { container: host });
}

function renderComponent(ui: React.ReactElement, dir: "ltr" | "rtl", locale: Locale) {
  const host = document.createElement("div");
  host.setAttribute("dir", dir);
  host.setAttribute("lang", locale);
  document.body.appendChild(host);

  return render(withIntl(ui, locale), { container: host });
}

describe.each(DIRECTIONS)("rendered pages · dir=$dir", ({ dir, locale }) => {
  it("Home has no WCAG 2.1 AA violations", async () => {
    const { container } = renderPage(<HomeScreen />, dir, locale);

    expect(await axePage(container)).toHaveNoViolations();
  });
});

describe.each(DIRECTIONS)("DataTable · dir=$dir", ({ dir, locale }) => {
  it("fold mode has no violations", async () => {
    const { container } = renderComponent(
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
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("scroll mode has no violations, including the focusable scroll region", async () => {
    const { container } = renderComponent(
      <DataTable
        caption="Audit log"
        mode="scroll"
        columns={AUDIT_COLUMNS}
        rows={ROWS}
        getRowId={getRowId}
      />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the empty table has no violations", async () => {
    const { container } = renderComponent(
      <DataTable
        caption="Tickets"
        columns={COLUMNS}
        rows={[]}
        getRowId={getRowId}
        emptyState={<EmptyState headline="No data for this selection" />}
      />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });
});

describe.each(DIRECTIONS)("domain states · dir=$dir", ({ dir, locale }) => {
  it("EmptyState has no violations", async () => {
    const { container } = renderComponent(
      <EmptyState headline="No data for this selection" description="Nothing matched." />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("ForbiddenState has no violations", async () => {
    const { container } = renderComponent(
      <ForbiddenState
        headline="You do not have access to this data"
        description="Deliveries is outside your scope."
        withheldLabel="tickets"
        reference="ERR-SCOPE-403"
      />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });
});

describe.each(DIRECTIONS)("form primitives · dir=$dir", ({ dir, locale }) => {
  it("FileInput has no violations and is properly labelled", async () => {
    const { container } = renderComponent(
      <FileInput
        label="Attach a photo"
        accept="image/*"
        capture="environment"
        description="PNG or JPEG, up to 10 MB."
      />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });
});

describe.each(DIRECTIONS)("global chrome · dir=$dir", ({ dir, locale }) => {
  it("AppShell has no violations", async () => {
    const { container } = renderComponent(
      <AppShell>
        <HomeScreen />
      </AppShell>,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });
});

describe("the gate actually bites", () => {
  it("reports a violation on a known-bad fixture", async () => {
    /*
     * Guard-the-guard. A misconfigured axe that passes everything looks exactly
     * like a healthy codebase, so this asserts the gate can fail: an image with
     * no alt text and a button with no accessible name are both WCAG A
     * failures.
     */
    const { container } = renderComponent(
      <div>
        {/* eslint-disable-next-line @next/next/no-img-element, jsx-a11y/alt-text -- the missing alt IS the fixture */}
        <img src="/x.png" />
        <button type="button" />
      </div>,
      "ltr",
      "en",
    );

    const results = await axe(container);

    expect(results.violations.length).toBeGreaterThan(0);
    expect(results.violations.map((violation) => violation.id)).toContain("image-alt");
  });
});
