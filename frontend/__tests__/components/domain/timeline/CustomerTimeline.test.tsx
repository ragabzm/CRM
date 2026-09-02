import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/customers/01AAAAAAAAAAAAAAAAAAAAAAAA",
}));

import { CustomerTimeline } from "@/components/domain/CustomerTimeline/CustomerTimeline";
import type { TimelineEntry } from "@/lib/api/customers";

const CUSTOMER = "01AAAAAAAAAAAAAAAAAAAAAAAA";

function entry(overrides: Partial<TimelineEntry> = {}): TimelineEntry {
  return {
    id: "01E1",
    kind: "ticket_opened",
    ticket_id: "01TICKET0000000000000000AA",
    ticket_ref: "TKT-000042",
    occurred_at: "2026-09-02T09:15:00Z",
    preview: null,
    ...overrides,
  };
}

const PAGE_ONE: TimelineEntry[] = [
  entry({ id: "01E3", kind: "message_outbound", preview: "Looking into it now.", occurred_at: "2026-09-02T11:00:00Z" }),
  entry({ id: "01E2", kind: "message_inbound", preview: "Any news?", occurred_at: "2026-09-02T10:00:00Z" }),
];

const PAGE_TWO: TimelineEntry[] = [entry({ id: "01E1" })];

let requested: string[] = [];
let status = 200;
let hasMore = true;

function json(body: unknown, code = 200) {
  return new Response(JSON.stringify(body), {
    status: code,
    headers: { "Content-Type": code >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  requested = [];
  status = 200;
  hasMore = true;
  sessionStorage.clear();

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);
      requested.push(url);

      if (status !== 200) {
        return json({ title: "no", status, code: "security.forbidden" }, status);
      }

      if (url.includes("cursor=")) {
        return json({ data: PAGE_TWO, next_cursor: null, has_more: false });
      }

      return json({
        data: PAGE_ONE,
        next_cursor: hasMore ? "Y3Vyc29yLTE=" : null,
        has_more: hasMore,
      });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const onOpenTicket = vi.fn();

const renderTimeline = () =>
  render(<CustomerTimeline customerId={CUSTOMER} onOpenTicket={onOpenTicket} />);

describe("the customer timeline", () => {
  it("shows tickets and messages in one list, newest first", async () => {
    renderTimeline();

    await screen.findByText("Outbound message");

    const rows = within(screen.getByRole("list")).getAllByRole("listitem");

    expect(rows.map((r) => r.getAttribute("data-kind"))).toEqual([
      "message_outbound",
      "message_inbound",
    ]);
  });

  it("names the kind in words, not only with an icon", async () => {
    renderTimeline();

    // Colour and glyph alone cannot say what a row is to a screen reader.
    expect(await screen.findByText("Outbound message")).toBeInTheDocument();
    expect(screen.getByText("Inbound message")).toBeInTheDocument();
  });

  it("shows the ticket reference and the time on every row", async () => {
    renderTimeline();
    await screen.findByText("Outbound message");

    const row = within(screen.getByRole("list")).getAllByRole("listitem")[0]!;

    expect(within(row).getByText("TKT-000042")).toBeInTheDocument();
    expect(within(row).getByRole("time")).toHaveAttribute("datetime", "2026-09-02T11:00:00Z");
  });

  it("opens the ticket a row refers to", async () => {
    renderTimeline();
    await screen.findByText("Outbound message");

    await userEvent.click(screen.getAllByRole("button", { name: /Open TKT-000042/ })[0]!);

    expect(onOpenTicket).toHaveBeenCalledWith("01TICKET0000000000000000AA");
  });

  it("loads the next page on request and appends it", async () => {
    renderTimeline();
    await screen.findByText("Outbound message");

    await userEvent.click(screen.getByRole("button", { name: "Load more" }));

    await waitFor(() => expect(screen.getByText("Ticket opened")).toBeInTheDocument());

    // Appended, not replaced.
    expect(screen.getByText("Outbound message")).toBeInTheDocument();
    expect(requested.at(-1)).toContain("cursor=");
  });

  it("announces what arrived, since it appends below the fold", async () => {
    renderTimeline();
    await screen.findByText("Outbound message");

    await userEvent.click(screen.getByRole("button", { name: "Load more" }));

    const status = await screen.findByRole("status");

    await waitFor(() => expect(status).toHaveTextContent("1 more entries loaded."));
    expect(status).toHaveAttribute("aria-live", "polite");
  });

  it("stops offering more once there is none", async () => {
    hasMore = false;

    renderTimeline();
    await screen.findByText("Outbound message");

    // A button that fetches nothing teaches the reader to distrust it.
    expect(screen.queryByRole("button", { name: "Load more" })).toBeNull();
  });

  it("never loads automatically on scroll", async () => {
    renderTimeline();
    await screen.findByText("Outbound message");

    const before = requested.length;
    screen.getByRole("list").dispatchEvent(new Event("scroll", { bubbles: true }));

    // Infinite scroll strands a keyboard user and never lets a phone reach
    // anything below the list.
    await waitFor(() => expect(requested.length).toBe(before));
    expect(screen.getByRole("button", { name: "Load more" })).toBeInTheDocument();
  });

  it("shows an empty state rather than a bare list", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => json({ data: [], next_cursor: null, has_more: false })),
    );

    renderTimeline();

    expect(await screen.findByText("No history yet")).toBeInTheDocument();
    expect(screen.queryByRole("list")).toBeNull();
  });

  it("renders the forbidden surface when refused", async () => {
    status = 403;

    renderTimeline();

    expect(
      await screen.findByText("You do not have access to this history"),
    ).toBeInTheDocument();
    expect(screen.queryByRole("list")).toBeNull();
  });

  it("offers a retry inline when a load fails", async () => {
    status = 500;

    renderTimeline();

    const alert = await screen.findByRole("alert");

    // The pane owns its own error — not a toast that vanishes while the reader
    // is looking elsewhere.
    expect(alert).toHaveTextContent("The timeline couldn't be loaded.");

    status = 200;
    await userEvent.click(within(alert).getByRole("button", { name: "Retry" }));

    expect(await screen.findByText("Outbound message")).toBeInTheDocument();
  });

  it("offers no filters of any kind", async () => {
    renderTimeline();
    await screen.findByText("Outbound message");

    // No channel filter, no date filter, no lane markers — one list read top
    // to bottom.
    expect(screen.queryByRole("combobox")).toBeNull();
    expect(screen.queryByRole("searchbox")).toBeNull();
    expect(screen.queryByLabelText(/channel/i)).toBeNull();
    expect(screen.queryByLabelText(/from|to|date/i)).toBeNull();
  });

  it("wraps long previews instead of clipping them", async () => {
    renderTimeline();
    await screen.findByText("Looking into it now.");

    const preview = screen.getByText("Looking into it now.").closest("p");

    // At 375 px a clipped line hides content with no way to reach it.
    expect(preview?.className).toContain("whitespace-pre-wrap");
    expect(preview?.className).not.toContain("truncate");
  });
});
