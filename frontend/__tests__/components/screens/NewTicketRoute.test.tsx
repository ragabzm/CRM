import { describe, expect, it, vi } from "vitest";

import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import { NewTicketScreen } from "@/components/screens/tickets/NewTicketScreen";
import { TicketListScreen } from "@/components/screens/tickets/TicketListScreen";

/**
 * An agent can get to the new-ticket form, and it works when they do.
 *
 * The form was written, tested and marked done, and no route ever imported
 * it — so for two stories the only tickets in the system came from the portal
 * or from email. `every-screen-is-reachable` now catches the import half of
 * that. This is the other half: a route with nothing linking to it is still
 * somewhere nobody can get to, and no import graph can tell you that.
 */

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), refresh: vi.fn() }),
  usePathname: () => "/tickets",
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("@/lib/api/tickets", async () => {
  const actual = await vi.importActual<typeof import("@/lib/api/tickets")>("@/lib/api/tickets");

  return {
    ...actual,
    listTickets: vi.fn().mockResolvedValue({
      data: [],
      meta: { total: 0, per_page: 25, current_page: 1, last_page: 1 },
      included: { assignees: {}, categories: {} },
    }),
    createTicket: vi.fn().mockResolvedValue({ id: "01TICKET" }),
  };
});

describe("the way in", () => {
  it("the ticket list offers a link to the form", async () => {
    render(<TicketListScreen params={{}} onParamsChange={() => {}} onOpen={() => {}} />);

    const link = await screen.findByRole("link", { name: /new ticket/i });

    // A link, and the right one. A button that pushed would not open in a new
    // tab, and could not be copied out of the address bar.
    expect(link).toHaveAttribute("href", "/tickets/new");
  });
});

describe("the new-ticket form", () => {
  it("says so when its options could not be loaded", () => {
    render(
      <NewTicketScreen categories={[]} departments={[]} referenceFailed onCreated={() => {}} />,
    );

    /*
     * The detail screen swallows this failure on purpose — an empty select
     * still leaves a readable ticket there. Here it leaves a form that cannot
     * be completed, and an empty Department select reads as "this business has
     * no departments" rather than "we could not reach the server".
     */
    expect(screen.getByRole("alert")).toBeInTheDocument();
  });

  it("says nothing when they loaded", () => {
    render(
      <NewTicketScreen
        categories={[{ id: 1, name: "Billing" }]}
        departments={[{ id: 1, name: "Support" }]}
        onCreated={() => {}}
      />,
    );

    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
  });

  it("offers the categories it was given", async () => {
    render(
      <NewTicketScreen
        categories={[{ id: 7, name: "Billing" }]}
        departments={[{ id: 3, name: "Support" }]}
        onCreated={() => {}}
      />,
    );

    await waitFor(() => {
      expect(screen.getByRole("option", { name: "Billing" })).toBeInTheDocument();
    });

    expect(screen.getByRole("option", { name: "Support" })).toBeInTheDocument();
  });
});
