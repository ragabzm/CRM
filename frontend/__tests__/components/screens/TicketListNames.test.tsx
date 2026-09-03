import { describe, expect, it, vi } from "vitest";

import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import { AgentHomeScreen } from "@/components/screens/home/AgentHomeScreen";

/**
 * An assigned ticket says who has it.
 *
 * The Assignee and Category columns took their labels from props that no page
 * ever passed, so every row rendered a dash — and a ticket somebody was
 * working on looked exactly like one nobody had picked up. Nothing caught it
 * because every fixture in the suite had a null assignee and a null category,
 * which is also the only shape anyone had typed into the interface by hand.
 *
 * So this fixture deliberately has both set.
 */
vi.mock("@/lib/api/tickets", async () => {
  const actual = await vi.importActual<typeof import("@/lib/api/tickets")>("@/lib/api/tickets");

  return {
    ...actual,
    ticketCounts: vi.fn().mockResolvedValue({
      assigned_to_me: 1,
      unassigned: 0,
      at_risk: null,
      breached: null,
      pending_customer_reply: 0,
    }),
    listTickets: vi.fn().mockResolvedValue({
      data: [
        {
          id: "01AAA",
          reference: "TKT-000043",
          subject: "Duplicate charge",
          description: "",
          customer_id: "01CCC",
          channel: "email",
          status: "open",
          priority: "high",
          category_id: 2,
          assignee_id: 4,
          department_id: 1,
          creator_type: "system",
          creator_id: null,
          version: 1,
          created_at: "2026-09-03T10:00:00+00:00",
          updated_at: "2026-09-03T10:00:00+00:00",
          sla: null,
        },
      ],
      meta: { total: 1, per_page: 25, current_page: 1, last_page: 1 },
      included: {
        assignees: { "4": "Hana Support" },
        categories: { "2": "Billing" },
      },
    }),
  };
});

describe("the ticket queue", () => {
  it("names the person holding the ticket", async () => {
    render(<AgentHomeScreen currentUserId={4} onOpen={() => {}} />);

    await waitFor(() => {
      expect(screen.getByText("Hana Support")).toBeInTheDocument();
    });
  });

  it("names the category rather than showing its id", async () => {
    render(<AgentHomeScreen currentUserId={4} onOpen={() => {}} />);

    await waitFor(() => {
      expect(screen.getByText("Billing")).toBeInTheDocument();
    });

    expect(screen.queryByText("2")).not.toBeInTheDocument();
  });
});
