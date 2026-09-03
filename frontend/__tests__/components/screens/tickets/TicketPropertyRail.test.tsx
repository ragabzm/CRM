import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/tickets/01T1",
}));

import { TicketPropertyRail } from "@/components/domain/TicketPropertyRail/TicketPropertyRail";
import type { Ticket } from "@/lib/api/tickets";

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
} as unknown as Ticket;

let requests: Array<{ method: string; url: string; headers: Record<string, string> }> = [];
let conflict = false;

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  requests = [];
  conflict = false;

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);

      if (url.includes("/sanctum/csrf-cookie")) return new Response(null, { status: 204 });

      requests.push({
        method: init?.method ?? "GET",
        url,
        headers: (init?.headers ?? {}) as Record<string, string>,
      });

      if (conflict) {
        return json(
          {
            status: 409,
            code: "tickets.stale_version",
            title: "This ticket was changed by someone else",
            current_version: 5,
            submitted_version: 3,
          },
          409,
        );
      }

      return json({ ...TICKET, priority: "urgent", version: 4 });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const renderRail = (props: Partial<React.ComponentProps<typeof TicketPropertyRail>> = {}) =>
  render(
    <TicketPropertyRail
      ticket={TICKET}
      categories={[{ id: 1, name: "Billing" }]}
      assignees={[{ id: 7, name: "Dana Faris" }]}
      departments={[{ id: 2, name: "Support" }]}
      editable
      onChanged={vi.fn()}
      onReload={vi.fn()}
      {...props}
    />,
  );

describe("the property rail", () => {
  it("offers every contended field", () => {
    renderRail();

    for (const label of ["Status", "Priority", "Category", "Assignee", "Department"]) {
      expect(screen.getByRole("combobox", { name: label })).toBeInTheDocument();
    }
  });

  it("shows the service level without letting anyone type it", () => {
    renderRail();

    /*
     * Derived — from the targets, the working-hours schedule and what actually
     * happened on the ticket. An agent who could type it could promise the
     * customer something the business never agreed to, so it is rendered as a
     * reading rather than as a field.
     */
    const sla = screen.getByRole("region", { name: "Service level" });

    expect(sla).toBeInTheDocument();
    expect(within(sla).queryByRole("textbox")).toBeNull();
    expect(within(sla).queryByRole("combobox")).toBeNull();
  });

  it("shows a dash for a ticket nothing is tracking", () => {
    renderRail();

    const sla = screen.getByRole("region", { name: "Service level" });

    // Never "on track": this fixture carries no SLA block, and a green badge
    // would be a claim the system cannot support.
    expect(within(sla).getByText("Not tracked")).toBeInTheDocument();
  });

  it("sends the version it was loaded with, as If-Match", async () => {
    renderRail();

    await userEvent.click(screen.getByRole("combobox", { name: "Priority" }));
    await userEvent.click(await screen.findByRole("option", { name: "urgent" }));

    await waitFor(() => expect(requests.some((r) => r.method === "PATCH")).toBe(true));

    const patch = requests.find((r) => r.method === "PATCH")!;

    // Echoing the ETag the read handed back. Without it this write would
    // silently revert whatever someone else did in the meantime.
    expect(patch.headers["If-Match"]).toBe('W/"3"');
  });

  it("reports a conflict instead of overwriting", async () => {
    conflict = true;
    renderRail();

    await userEvent.click(screen.getByRole("combobox", { name: "Priority" }));
    await userEvent.click(await screen.findByRole("option", { name: "urgent" }));

    const alert = await screen.findByRole("alert");

    expect(alert).toHaveTextContent("This ticket was changed by someone else");
  });

  it("offers a reload that refetches", async () => {
    conflict = true;
    const onReload = vi.fn();
    renderRail({ onReload });

    await userEvent.click(screen.getByRole("combobox", { name: "Priority" }));
    await userEvent.click(await screen.findByRole("option", { name: "urgent" }));

    const alert = await screen.findByRole("alert");
    await userEvent.click(within(alert).getByRole("button", { name: "Reload" }));

    expect(onReload).toHaveBeenCalled();
  });

  it("promises the draft is kept when it reports a conflict", async () => {
    conflict = true;
    renderRail();

    await userEvent.click(screen.getByRole("combobox", { name: "Priority" }));
    await userEvent.click(await screen.findByRole("option", { name: "urgent" }));

    // Said out loud, because the agent's next thought is "did I just lose what
    // I was writing?"
    expect(await screen.findByRole("alert")).toHaveTextContent("Your draft reply is kept");
  });

  it("hands the updated ticket back on success", async () => {
    const onChanged = vi.fn();
    renderRail({ onChanged });

    await userEvent.click(screen.getByRole("combobox", { name: "Priority" }));
    await userEvent.click(await screen.findByRole("option", { name: "urgent" }));

    await waitFor(() => expect(onChanged).toHaveBeenCalled());
    expect(onChanged.mock.calls[0]![0].version).toBe(4);
  });

  it("is readable but not editable without the permission", () => {
    renderRail({ editable: false });

    // Reading a ticket and changing it are different permissions; holding only
    // the first should not hide the ticket.
    expect(screen.getByRole("combobox", { name: "Status" })).toBeDisabled();
    expect(screen.getByText("You can read this ticket but not change it.")).toBeInTheDocument();
  });

  it("writes nothing while read-only", async () => {
    renderRail({ editable: false });

    await userEvent.click(screen.getByRole("combobox", { name: "Priority" }));

    expect(requests.filter((r) => r.method === "PATCH")).toHaveLength(0);
  });
});
