import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn(), replace: vi.fn() }),
  usePathname: () => "/tickets",
}));

import { TicketListScreen } from "@/components/screens/tickets/TicketListScreen";
import en from "@/messages/en.json";

function ticket(overrides: Record<string, unknown> = {}) {
  return {
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
    version: 1,
    created_at: "2026-09-01T08:00:00Z",
    updated_at: "2026-09-02T08:00:00Z",
    ...overrides,
  };
}

let urls: string[] = [];
let status = 200;
let rows = [ticket()];

function json(body: unknown, code = 200) {
  return new Response(JSON.stringify(body), {
    status: code,
    headers: { "Content-Type": code >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  urls = [];
  status = 200;
  rows = [ticket()];

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      urls.push(String(input));

      if (status !== 200) return json({ status, code: "x" }, status);

      return json({
        data: rows,
        meta: { total: rows.length, per_page: 25, current_page: 1, last_page: 1 },
      });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const renderList = (props: Partial<React.ComponentProps<typeof TicketListScreen>> = {}) =>
  render(<TicketListScreen params={{}} onParamsChange={vi.fn()} onOpen={vi.fn()} {...props} />);

describe("the ticket list", () => {
  it("shows the tickets it was given", async () => {
    renderList();

    expect(await screen.findByText("TKT-000042")).toBeInTheDocument();
    expect(screen.getByText("Duplicate charge")).toBeInTheDocument();
  });

  it("sends the filters it was handed", async () => {
    renderList({ params: { status: ["open", "pending"], assignee_id: ["unassigned"] } });

    await waitFor(() => expect(urls).not.toHaveLength(0));

    // Comma-separated, so the request and the address bar say the same thing.
    expect(urls[0]).toContain("status=open%2Cpending");
    expect(urls[0]).toContain("assignee_id=unassigned");
  });

  it("hands a filter change back rather than keeping it", async () => {
    const onParamsChange = vi.fn();
    renderList({ onParamsChange });

    await screen.findByText("TKT-000042");

    await userEvent.click(
      within(screen.getByRole("group", { name: en.tickets.filters.assignee })).getByRole("button", {
        name: en.tickets.filters.unassigned,
      }),
    );

    // The URL is the only place list state lives — the screen never holds it.
    expect(onParamsChange).toHaveBeenCalledWith(
      expect.objectContaining({ assignee_id: ["unassigned"] }),
    );
  });

  it("shows the SLA column, and a dash when nothing is tracking", async () => {
    renderList();

    await screen.findByText("TKT-000042");

    /*
     * The column Story 4.5 reserved and drew as a dash now carries the real
     * reading — and still draws a dash when the engine is not tracking,
     * because "we do not know" and "fine" are different answers. This fixture
     * has no `sla` block, which is exactly that case.
     */
    expect(screen.getByText(en.tickets.columns.sla)).toBeInTheDocument();
    expect(document.querySelector('[data-slot="sla-indicator"]')).toHaveAttribute(
      "data-state",
      "not-tracked",
    );
  });

  it("shows a real SLA reading when the engine is tracking", async () => {
    rows = [
      ticket({
        sla: {
          state: "at_risk",
          response: {
            state: "at_risk",
            elapsed_minutes: 50,
            target_minutes: 60,
            remaining_minutes: 10,
            due_at: "2026-09-06T09:00:00Z",
          },
          resolution: {
            state: "on_track",
            elapsed_minutes: 50,
            target_minutes: 240,
            remaining_minutes: 190,
            due_at: "2026-09-06T13:00:00Z",
          },
        },
      }),
    ];

    renderList();

    await screen.findByText("TKT-000042");

    expect(screen.getByText(en.tickets.sla.state.at_risk)).toBeInTheDocument();
  });

  it("says there are no matches rather than showing a bare table", async () => {
    rows = [];
    renderList();

    expect(await screen.findByText(en.tickets.empty.title)).toBeInTheDocument();
  });

  it("says access is refused, which is a different thing", async () => {
    status = 403;
    renderList();

    // "No matches" and "not allowed" must never be confused: one invites a
    // wider search, the other invites a conversation with a supervisor.
    expect(await screen.findByText(en.tickets.forbidden.title)).toBeInTheDocument();
    expect(screen.queryByText(en.tickets.empty.title)).toBeNull();
  });

  it("offers a retry when the first load fails", async () => {
    status = 500;
    renderList();

    const alert = await screen.findByRole("alert");

    expect(within(alert).getByRole("button", { name: en.tickets.retry })).toBeInTheDocument();
  });

  it("opens the ticket a row points at", async () => {
    const onOpen = vi.fn();
    renderList({ onOpen });

    await screen.findByText("TKT-000042");

    await userEvent.click(screen.getByRole("button", { name: /TKT-000042/ }));
    await userEvent.click(await screen.findByRole("menuitem", { name: en.tickets.open }));

    expect(onOpen).toHaveBeenCalledWith("01T1");
  });

  it("shows an unassigned ticket as unassigned, not as blank", async () => {
    renderList();

    await screen.findByText("TKT-000042");

    // A blank cell reads as missing data; "Unassigned" is a fact.
    expect(screen.getAllByText(en.tickets.filters.unassigned).length).toBeGreaterThan(0);
  });

  it("keeps the reference readable in Arabic", async () => {
    render(<TicketListScreen params={{}} onParamsChange={vi.fn()} onOpen={vi.fn()} />, {
      locale: "ar",
    });

    const reference = await screen.findByText("TKT-000042");

    // Without isolation it renders as 000042-TKT inside Arabic text —
    // silently, and only in Arabic.
    expect(reference.getAttribute("dir")).toBe("ltr");
  });
});
