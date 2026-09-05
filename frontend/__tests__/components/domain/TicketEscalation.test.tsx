import { render, screen, waitFor, withIntl } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { fireEvent } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/tickets/01T1",
}));

import { TicketHeaderActions } from "@/components/domain/TicketHeaderActions/TicketHeaderActions";
import en from "@/messages/en.json";
import type { Ticket } from "@/lib/api/tickets";

/**
 * Saying a ticket is going wrong, and seeing that somebody has.
 *
 * The behaviour worth guarding is the ORDER of two things: the reason is asked
 * for before the escalation happens, and once it has happened the marker
 * carries the reason rather than a bare flag. A supervisor who has to open the
 * ticket to find out what is wrong has spent the time escalating was meant to
 * save.
 */

const TICKET: Ticket = {
  id: "01TICKET0000000000000000AA",
  reference: "TKT-000042",
  subject: "Invoice is wrong",
  description: "Charged twice.",
  customer_id: "01CUSTOMER00000000000000AA",
  channel: "email",
  status: "open",
  priority: "normal",
  category_id: null,
  assignee_id: 7,
  department_id: 1,
  creator_type: "staff",
  creator_id: "7",
  version: 4,
  created_at: "2026-09-01T09:00:00Z",
  updated_at: "2026-09-01T09:00:00Z",
  sla: null,
  channel_account_active: true,
  satisfaction: null,
  satisfaction_comment: null,
  satisfaction_at: null,
  escalated_at: null,
  escalated_by: null,
  escalation_reason: null,
};

let posted: Array<{ url: string; body: Record<string, unknown> }> = [];

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  posted = [];

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);

      if (url.includes("/sanctum/csrf-cookie")) return new Response(null, { status: 204 });

      posted.push({ url, body: JSON.parse(String(init?.body ?? "{}")) });

      return json({
        ...TICKET,
        escalated_at: "2026-09-05T10:00:00Z",
        escalated_by: "7",
        escalation_reason: String(
          (JSON.parse(String(init?.body ?? "{}")) as { reason?: string }).reason ?? "",
        ),
      });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

describe("escalating a ticket", () => {
  it("asks why before it does anything", async () => {
    render(<TicketHeaderActions ticket={TICKET} editable onChanged={vi.fn()} onReload={vi.fn()} />);

    await userEvent.click(screen.getByRole("button", { name: en.ticket.actions.escalate }));

    /*
     * The reason field appears and nothing has been sent. An escalation with
     * no reason reaches a supervisor as an alarm they cannot act on.
     */
    expect(screen.getByLabelText(en.ticket.actions.escalationReason)).toBeInTheDocument();
    expect(posted).toHaveLength(0);
  });

  it("sends the reason and nothing else", async () => {
    const onChanged = vi.fn();

    render(
      <TicketHeaderActions ticket={TICKET} editable onChanged={onChanged} onReload={vi.fn()} />,
    );

    await userEvent.click(screen.getByRole("button", { name: en.ticket.actions.escalate }));

    fireEvent.change(screen.getByLabelText(en.ticket.actions.escalationReason), {
      target: { value: "The customer has waited four days." },
    });

    await userEvent.click(screen.getByRole("button", { name: en.ticket.actions.escalate }));

    await waitFor(() => expect(posted).toHaveLength(1));

    expect(posted[0]!.url).toContain("/escalate");
    expect(posted[0]!.body).toEqual({ reason: "The customer has waited four days." });

    /*
     * No version. Two people escalating the same ticket agree — refusing the
     * second because a colleague got there first would be refusing the very
     * agreement that makes it worth escalating.
     */
    expect(posted[0]!.body).not.toHaveProperty("version");
    expect(onChanged).toHaveBeenCalled();
  });

  it("shows the reason once it is escalated, and stops offering the action", () => {
    render(
      withIntl(
        <TicketHeaderActions
          ticket={{
            ...TICKET,
            escalated_at: "2026-09-05T10:00:00Z",
            escalated_by: "7",
            escalation_reason: "The customer has waited four days.",
          }}
          editable
          onChanged={vi.fn()}
          onReload={vi.fn()}
        />,
      ),
    );

    // The reason, not just a flag: a supervisor should not have to open the
    // ticket to learn what is wrong.
    expect(screen.getByText(/The customer has waited four days\./)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: en.ticket.actions.escalate })).toBeNull();
  });

  it("leaves the status alone — escalation is not a state", () => {
    render(
      withIntl(
        <TicketHeaderActions
          ticket={{ ...TICKET, escalated_at: "2026-09-05T10:00:00Z" }}
          editable
          onChanged={vi.fn()}
          onReload={vi.fn()}
        />,
      ),
    );

    /*
     * Resolve is still the primary action on an escalated ticket. An
     * escalated ticket is still open, and a screen that swapped its lifecycle
     * controls for an escalation banner would have made escalation a status
     * in everything but name.
     */
    expect(screen.getByRole("button", { name: en.ticket.actions.resolve })).toBeInTheDocument();
  });

  it("offers nothing at all to a reader who may not change the ticket", () => {
    render(
      <TicketHeaderActions
        ticket={TICKET}
        editable={false}
        onChanged={vi.fn()}
        onReload={vi.fn()}
      />,
    );

    expect(screen.queryByRole("button", { name: en.ticket.actions.escalate })).toBeNull();
  });
});
