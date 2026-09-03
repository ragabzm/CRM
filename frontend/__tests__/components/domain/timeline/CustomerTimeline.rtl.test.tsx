import { render, screen, within } from "@/__tests__/helpers/intl";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/customers/01AAAAAAAAAAAAAAAAAAAAAAAA",
}));

import { CustomerTimeline } from "@/components/domain/CustomerTimeline/CustomerTimeline";
import ar from "@/messages/ar.json";

/**
 * Arabic renders right-to-left, with Gregorian dates and Western digits.
 *
 * The product is bilingual but not bi-calendar: an agent reading Arabic and a
 * customer reading English must be able to quote the same date to each other.
 * Hijri dates or Arabic-Indic digits would make "2 September" and its Arabic
 * rendering two different-looking facts.
 */

const ENTRIES = [
  {
    id: "01E1",
    kind: "message_inbound" as const,
    ticket_id: "01TICKET0000000000000000AA",
    ticket_ref: "TKT-000042",
    occurred_at: "2026-09-02T09:15:00Z",
    preview: "شكرًا، وصلت الفاتورة",
  },
];

beforeEach(() => {
  sessionStorage.clear();

  vi.stubGlobal(
    "fetch",
    vi.fn(
      async () =>
        new Response(JSON.stringify({ data: ENTRIES, next_cursor: null, has_more: false }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        }),
    ),
  );
});

afterEach(() => vi.unstubAllGlobals());

function renderInArabic() {
  const host = document.createElement("div");
  host.setAttribute("dir", "rtl");
  host.setAttribute("lang", "ar");
  document.body.appendChild(host);

  return render(
    <CustomerTimeline customerId="01AAAAAAAAAAAAAAAAAAAAAAAA" onOpenTicket={vi.fn()} />,
    {
      locale: "ar",
      container: host,
    },
  );
}

describe("the timeline in Arabic", () => {
  it("uses the Arabic copy", async () => {
    renderInArabic();

    expect(await screen.findByText(ar.timeline.entry.messageInbound)).toBeInTheDocument();
    expect(screen.getByRole("list", { name: ar.timeline.title })).toBeInTheDocument();
  });

  it("shows a Gregorian year, not a Hijri one", async () => {
    renderInArabic();
    await screen.findByText(ar.timeline.entry.messageInbound);

    const stamp = screen.getByRole("time").textContent ?? "";

    // 2026 Gregorian is 1447–1448 Hijri; seeing either of those would mean the
    // calendar switched with the language.
    expect(stamp).toContain("2026");
    expect(stamp).not.toContain("1447");
    expect(stamp).not.toContain("1448");
  });

  it("shows Western digits, not Arabic-Indic ones", async () => {
    renderInArabic();
    await screen.findByText(ar.timeline.entry.messageInbound);

    const stamp = screen.getByRole("time").textContent ?? "";

    // ٠١٢٣٤٥٦٧٨٩ would make a date unquotable between an Arabic reader and an
    // English one.
    expect(stamp).toMatch(/\d/);
    expect(stamp).not.toMatch(/[٠-٩۰-۹]/);
  });

  it("isolates the ticket reference so it does not reverse", async () => {
    renderInArabic();
    await screen.findByText(ar.timeline.entry.messageInbound);

    const reference = screen.getByText("TKT-000042");

    // Without isolation this renders as 000042-TKT inside Arabic prose —
    // silently, and only in Arabic.
    expect(reference.closest("bdi")).not.toBeNull();
  });

  it("mirrors by writing direction rather than by mirroring code", async () => {
    const { container } = renderInArabic();
    await screen.findByText(ar.timeline.entry.messageInbound);

    const row = within(screen.getByRole("list")).getAllByRole("listitem")[0]!;

    // Logical properties only: no `ml-`/`mr-`/`left-`/`right-` anywhere, so the
    // browser places everything and there is no second stylesheet to get wrong.
    expect(row.innerHTML).not.toMatch(/class="[^"]*\b(ml|mr|pl|pr|left|right)-/);
    expect(container.querySelector('[data-slot="customer-timeline"]')).not.toBeNull();
  });
});
