import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { en, render, screen, waitFor } from "@/__tests__/helpers/intl";
import { TicketListScreen } from "@/components/screens/tickets/TicketListScreen";

/**
 * What the row and the bar are supposed to look like.
 *
 * Every assertion here corresponds to a line in the design review: the row had
 * no badge of any kind, the reference had its own column, the filter bar mixed
 * two mechanisms, there was no pager, and the empty state was drawn while the
 * request was still in flight.
 */

const listTickets = vi.hoisted(() => vi.fn());

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  usePathname: () => "/tickets",
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("@/lib/api/tickets", async () => {
  const actual = await vi.importActual<typeof import("@/lib/api/tickets")>("@/lib/api/tickets");

  return { ...actual, listTickets };
});

const TICKET = {
  id: "01AAA",
  reference: "TKT-000043",
  subject: "Duplicate charge",
  description: "",
  customer_id: "01CCC",
  channel: "email" as const,
  status: "open" as const,
  priority: "urgent" as const,
  category_id: 2,
  assignee_id: 4,
  department_id: 1,
  creator_type: "system",
  creator_id: null,
  version: 1,
  created_at: "2026-09-03T10:00:00+00:00",
  updated_at: "2026-09-03T10:00:00+00:00",
  sla: null,
};

function page(over: Record<string, unknown> = {}) {
  return {
    data: [TICKET],
    meta: { total: 1, per_page: 25, current_page: 1, last_page: 1 },
    included: { assignees: { "4": "Hana Support" }, categories: { "2": "Billing" } },
    ...over,
  };
}

function renderList() {
  return render(
    <TicketListScreen
      params={{}}
      onParamsChange={() => {}}
      onOpen={() => {}}
      categories={[{ id: 2, name: "Billing" }]}
      departments={[{ id: 1, name: "Support" }]}
      assignees={[{ id: 4, name: "Hana Support" }]}
    />,
  );
}

describe("the ticket row", () => {
  it("carries a status badge rather than plain text", async () => {
    listTickets.mockResolvedValue(page());
    renderList();

    await screen.findByText("Duplicate charge");

    /*
     * The design's rule is one badge per row, and the row had none — so every
     * value carried the same weight and the eye had nothing to catch on.
     */
    expect(document.querySelector('[data-slot="status-badge"]')).toHaveAttribute(
      "data-status",
      "open",
    );
  });

  it("carries an urgency meter rather than plain text", async () => {
    listTickets.mockResolvedValue(page());
    renderList();

    await screen.findByText("Duplicate charge");

    expect(document.querySelector('[data-slot="urgency-meter"]')).toHaveAttribute(
      "data-priority",
      "urgent",
    );
  });

  it("puts the reference and the channel under the subject, not in their own columns", async () => {
    listTickets.mockResolvedValue(page());
    renderList();

    const subject = await screen.findByText("Duplicate charge");
    const cell = subject.closest("td");

    // Both live in the subject cell now: the row identifies itself on one
    // line and explains itself on the next.
    expect(cell).toHaveTextContent("TKT-000043");
    expect(cell).toHaveTextContent(en.tickets.channel.email);
  });

  it("says how long ago, not the exact timestamp", async () => {
    listTickets.mockResolvedValue(page());
    renderList();

    await screen.findByText("Duplicate charge");

    const stamp = document.querySelector("time[datetime]");

    // The absolute moment stays reachable for anyone writing an incident note.
    expect(stamp).toHaveAttribute("title");
    expect(stamp?.textContent).not.toContain("2026");
  });
});

describe("the filter bar", () => {
  it("uses one mechanism for every filter", async () => {
    listTickets.mockResolvedValue(page());
    renderList();

    await screen.findByText("Duplicate charge");

    const bar = document.querySelector('[data-slot="ticket-filters"]');

    /*
     * Every filter is a labelled select and none is a button group. The bar
     * used to mix segmented groups (unlabelled) with selects (labelled), so
     * the same strip was read two ways and the word "Any" appeared three times
     * meaning three things.
     *
     * The COUNT is what changes when a filter is added — seven since Story
     * 10.1 added escalation — and the shape is what must not.
     */
    expect(bar?.querySelectorAll("select")).toHaveLength(7);
    expect(bar?.querySelectorAll('[role="group"]')).toHaveLength(0);
  });

  it("repeats what is chosen as a chip that removes itself", async () => {
    listTickets.mockResolvedValue(page());
    const onParamsChange = vi.fn();

    render(
      <TicketListScreen
        params={{ status: ["open"] }}
        onParamsChange={onParamsChange}
        onOpen={() => {}}
        categories={[]}
        departments={[]}
        assignees={[]}
      />,
    );

    const chip = await screen.findByRole("button", { name: /remove this filter/i });

    await userEvent.click(chip);

    await waitFor(() =>
      expect(onParamsChange).toHaveBeenCalledWith(expect.objectContaining({ status: [] })),
    );
  });
});

describe("the first paint", () => {
  it("draws placeholder rows rather than claiming there are no results", async () => {
    // A promise that never settles: this is the state the screen used to
    // answer with "No tickets match these filters".
    listTickets.mockReturnValue(new Promise(() => {}));

    renderList();

    await waitFor(() =>
      expect(document.querySelector('[data-slot="row-skeleton"]')).toBeInTheDocument(),
    );

    expect(screen.queryByText(en.tickets.empty.title)).not.toBeInTheDocument();
  });
});

describe("the footer", () => {
  it("says how much of the queue is on screen", async () => {
    listTickets.mockResolvedValue(
      page({ meta: { total: 128, per_page: 25, current_page: 1, last_page: 6 } }),
    );
    renderList();

    await screen.findByText("Duplicate charge");

    // The list had no footer at all, so 25 rows could have been the whole
    // queue or the first of six pages.
    expect(document.querySelector('[data-slot="pager"]')).toBeInTheDocument();
    expect(screen.getByText(/128/)).toBeInTheDocument();
  });
});
