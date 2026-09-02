import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/customers",
}));

import { render, screen } from "@testing-library/react";

import { withIntl } from "@/__tests__/helpers/intl";
import { ActionBar } from "@/components/domain/ActionBar/ActionBar";
import { CustomerTimeline } from "@/components/domain/CustomerTimeline/CustomerTimeline";
import { DuplicateOffer } from "@/components/domain/DuplicateOffer/DuplicateOffer";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import type { DuplicateMatch } from "@/lib/api/customers";
import type { Locale } from "@/lib/i18n/locale";

import { axe } from "./axe";

const DIRECTIONS: Array<{ dir: "ltr" | "rtl"; locale: Locale }> = [
  { dir: "ltr", locale: "en" },
  { dir: "rtl", locale: "ar" },
];

function renderIn(ui: React.ReactElement, dir: "ltr" | "rtl", locale: Locale) {
  const host = document.createElement("div");
  host.setAttribute("dir", dir);
  host.setAttribute("lang", locale);
  document.body.appendChild(host);

  return render(withIntl(ui, locale), { container: host });
}

const MATCHES: DuplicateMatch[] = [
  {
    customer_id: "01BBBBBBBBBBBBBBBBBBBBBBBB",
    reference: "C-9XQ4TR2M",
    full_name: "Hana Yousef",
    state: "inactive",
    matched_value: "hana@example.test",
    matched_kind: "email",
  },
];

describe.each(DIRECTIONS)("customer surfaces · dir=$dir", ({ dir, locale }) => {
  it("the duplicate offer has no WCAG 2.1 AA violations", async () => {
    const { container } = renderIn(
      <DuplicateOffer matches={MATCHES} onOpenExisting={vi.fn()} onCreateAnyway={vi.fn()} />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the segmented filter has no violations", async () => {
    const { container } = renderIn(
      <SegmentedFilter
        label="State"
        value="active"
        options={[
          { value: "active", label: "Active" },
          { value: "inactive", label: "Inactive" },
        ]}
        onChange={vi.fn()}
      />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the action bar has no violations", async () => {
    const { container } = renderIn(
      <ActionBar
        actions={[
          { id: "edit", label: "Edit", onSelect: vi.fn() },
          { id: "deactivate", label: "Deactivate", destructive: true, onSelect: vi.fn() },
        ]}
      />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });
});

const TIMELINE = [
  {
    id: "01E1",
    kind: "ticket_opened" as const,
    ticket_id: "01TICKET0000000000000000AA",
    ticket_ref: "TKT-000042",
    occurred_at: "2026-09-01T08:00:00Z",
    preview: null,
  },
  {
    id: "01E2",
    kind: "message_inbound" as const,
    ticket_id: "01TICKET0000000000000000AA",
    ticket_ref: "TKT-000042",
    occurred_at: "2026-09-02T09:15:00Z",
    preview: "The invoice still shows last month's total.",
  },
];

describe.each(DIRECTIONS)("the customer timeline · dir=$dir", ({ dir, locale }) => {
  beforeEach(() => {
    sessionStorage.clear();

    vi.stubGlobal(
      "fetch",
      vi.fn(
        async () =>
          new Response(JSON.stringify({ data: TIMELINE, next_cursor: "Y3Vyc29y", has_more: true }), {
            status: 200,
            headers: { "Content-Type": "application/json" },
          }),
      ),
    );
  });

  afterEach(() => vi.unstubAllGlobals());

  it("has no WCAG 2.1 AA violations once loaded", async () => {
    const { container } = renderIn(
      <CustomerTimeline customerId="01AAAAAAAAAAAAAAAAAAAAAAAA" onOpenTicket={vi.fn()} />,
      dir,
      locale,
    );

    // The interesting state is the loaded one: a labelled list, a live region,
    // and a "load more" control that must stay reachable in both directions.
    await screen.findByRole("list");

    expect(await axe(container)).toHaveNoViolations();
  });
});
