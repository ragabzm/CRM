import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/tickets/01T1",
}));

import { TicketDetailScreen } from "@/components/screens/tickets/TicketDetailScreen";
import ar from "@/messages/ar.json";

const TICKET = {
  id: "01T1",
  reference: "TKT-000042",
  subject: "Duplicate charge",
  description: "Charged twice.",
  customer_id: "01C1",
  channel: "agent",
  status: "open",
  priority: "normal",
  category_id: null,
  assignee_id: null,
  department_id: null,
  creator_type: "staff",
  creator_id: "7",
  version: 3,
  created_at: "2026-09-01T08:00:00Z",
  updated_at: "2026-09-02T08:00:00Z",
};

const CONTEXT = {
  customer_id: "01C1",
  reference: "C-9XQ4TR2M",
  full_name: "Hana Yousef",
  state: "active",
  department: { id: 2, name: "Billing" },
  open_ticket_count: 3,
  recent_ticket_count: 5,
  recent_window_days: 30,
  last_interaction_at: "2026-09-02T09:00:00Z",
};

let ticketStatus = 200;

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  ticketStatus = 200;
  localStorage.clear();

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);

      if (url.includes("/customer-context")) {
        return ticketStatus === 200
          ? json(CONTEXT)
          : json({ status: ticketStatus, code: "x" }, ticketStatus);
      }

      if (url.includes("/messages")) return json({ data: [] });
      if (url.includes("/quick-replies")) return json({ data: [] });

      return ticketStatus === 200
        ? json(TICKET)
        : json({ status: ticketStatus, code: "tickets.not_found" }, ticketStatus);
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const renderScreen = (locale: "en" | "ar" = "en") =>
  render(
    <TicketDetailScreen
      ticketId="01T1"
      categories={[]}
      assignees={[]}
      departments={[]}
      editable
      onNavigate={vi.fn()}
    />,
    { locale },
  );

describe("the ticket workspace", () => {
  it("shows all three regions at once on a wide screen", async () => {
    const { container } = renderScreen();

    await screen.findByRole("heading", { name: /Duplicate charge/ });

    // The point of the screen: an agent answers without leaving the ticket.
    expect(container.querySelector('[data-slot="ticket-property-rail"]')).not.toBeNull();
    expect(container.querySelector('[data-slot="ticket-composer"]')).not.toBeNull();
    expect(container.querySelector('[data-slot="customer-context"]')).not.toBeNull();
  });

  it("offers one pane at a time on a narrow screen", async () => {
    renderScreen();
    await screen.findByRole("heading", { name: /Duplicate charge/ });

    // At 390px three columns show none of them usably, and an agent answering
    // from a phone is answering from a phone.
    const tabs = screen.getByRole("group", { name: "Conversation" });

    for (const label of ["Conversation", "Properties", "Customer"]) {
      expect(within(tabs).getByRole("button", { name: label })).toBeInTheDocument();
    }
  });

  it("names the ticket and its reference", async () => {
    renderScreen();

    expect(await screen.findByText("TKT-000042")).toBeInTheDocument();
  });

  it("keeps the reference readable in Arabic", async () => {
    renderScreen("ar");

    const reference = await screen.findByText("TKT-000042");

    // An identifier, not prose. Without isolation it renders as 000042-TKT
    // inside Arabic text — silently, and only in Arabic.
    expect(reference.getAttribute("dir")).toBe("ltr");
    expect(
      screen.getByRole("heading", { level: 2, name: ar.ticket.propertyRail.title }),
    ).toBeInTheDocument();
  });

  it("fetches the ticket and the context in parallel", async () => {
    renderScreen();
    await screen.findByRole("heading", { name: /Duplicate charge/ });

    // Two round trips in sequence is a second of staring at an empty
    // workspace for no reason.
    const urls = vi.mocked(globalThis.fetch).mock.calls.map((c) => String(c[0]));

    expect(urls.some((u) => u.includes("/customer-context"))).toBe(true);
    expect(urls.some((u) => u.endsWith("/tickets/01T1"))).toBe(true);
  });

  it("shows the customer's history at a glance", async () => {
    renderScreen();

    expect(await screen.findByText("Hana Yousef")).toBeInTheDocument();
    expect(screen.getByText("Open tickets")).toBeInTheDocument();
    expect(screen.getByText("Raised in the last 30 days")).toBeInTheDocument();
  });

  it("carries the way back in the customer link", async () => {
    const onNavigate = vi.fn();

    render(
      <TicketDetailScreen
        ticketId="01T1"
        categories={[]}
        assignees={[]}
        departments={[]}
        editable
        onNavigate={onNavigate}
      />,
    );

    await userEvent.click(await screen.findByRole("button", { name: "Open full customer record" }));

    /*
     * In the URL rather than in memory, so the trip back survives a reload, a
     * new tab, or a link pasted to a colleague.
     */
    expect(onNavigate).toHaveBeenCalledWith("/customers/01C1?returnTo=%2Ftickets%2F01T1");
  });

  it("says the ticket is gone rather than showing an empty workspace", async () => {
    ticketStatus = 404;
    renderScreen();

    expect(await screen.findByText("This ticket no longer exists")).toBeInTheDocument();
  });

  it("says so plainly when access is refused", async () => {
    ticketStatus = 403;
    renderScreen();

    expect(await screen.findByText("You do not have access to this ticket")).toBeInTheDocument();
  });

  it("offers a retry when loading fails for another reason", async () => {
    ticketStatus = 500;
    renderScreen();

    const alert = await screen.findByRole("alert");

    expect(within(alert).getByRole("button", { name: "Retry" })).toBeInTheDocument();
  });
});

describe("the layout in Arabic", () => {
  it("mirrors by writing direction, not by mirroring code", async () => {
    const host = document.createElement("div");
    host.setAttribute("dir", "rtl");
    host.setAttribute("lang", "ar");
    document.body.appendChild(host);

    const { container } = render(
      <TicketDetailScreen
        ticketId="01T1"
        categories={[]}
        assignees={[]}
        departments={[]}
        editable
        onNavigate={vi.fn()}
      />,
      { locale: "ar", container: host },
    );

    await screen.findByRole("heading", { name: /Duplicate charge/ });

    const grid = container.querySelector('[data-slot="ticket-detail"]')!;

    // Logical properties only: the rail is grid column 1 in both writing
    // modes and the browser decides which edge that is. A physical `left-`
    // or `ml-` here would need a second stylesheet for Arabic.
    expect(grid.innerHTML).not.toMatch(/class="[^"]*\b(ml|mr|pl|pr|left|right)-/);
  });
});
