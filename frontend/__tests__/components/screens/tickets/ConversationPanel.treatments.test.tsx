import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/tickets/01T1",
}));

import { ConversationPanel } from "@/components/domain/TicketConversation/ConversationPanel";
import ar from "@/messages/ar.json";
import en from "@/messages/en.json";

/**
 * Three treatments, and no fourth.
 *
 * A reader has to be able to tell, without reading a word, whether a message
 * came from the customer, went to them, or was never meant to leave the
 * building. The third is the one that matters: mistaking an internal note for a
 * reply is how a colleague's private remark reaches the person it is about.
 */

const MESSAGES = [
  {
    id: "01M1",
    ticket_id: "01T1",
    direction: "inbound" as const,
    author: { type: "customer", id: "01C1", name: "Hana Yousef" },
    body: "My invoice is wrong.",
    sent_at: "2026-09-02T09:00:00Z",
    delivery_state: null,
    attachments: [],
  },
  {
    id: "01M2",
    ticket_id: "01T1",
    direction: "outbound" as const,
    author: { type: "staff", id: "7", name: "Dana Faris" },
    body: "Looking into it now.",
    sent_at: "2026-09-02T09:30:00Z",
    delivery_state: "sent" as const,
    attachments: [],
  },
  {
    id: "01M3",
    ticket_id: "01T1",
    direction: "internal" as const,
    author: { type: "staff", id: "7", name: "Dana Faris" },
    body: "Second time this month — check the billing run.",
    sent_at: "2026-09-02T09:31:00Z",
    delivery_state: null,
    attachments: [],
  },
];

let calls: string[] = [];
let failOne = false;

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

beforeEach(() => {
  calls = [];
  failOne = false;

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      calls.push(`${init?.method ?? "GET"} ${url}`);

      if (url.includes("/retry")) {
        return json({ ...MESSAGES[1], id: "01M2", delivery_state: "queued" });
      }

      const data = failOne ? [{ ...MESSAGES[1], delivery_state: "failed" }] : MESSAGES;

      return json({ data });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const renderPanel = (onEdit = vi.fn()) =>
  render(<ConversationPanel ticketId="01T1" onEditFailed={onEdit} />);

describe("the three conversation treatments", () => {
  it("names each kind in words, not only by colour or position", async () => {
    renderPanel();

    // Colour and side say nothing to a screen reader, and nothing in greyscale.
    expect(await screen.findByText(en.ticket.conversation.inbound)).toBeInTheDocument();
    expect(screen.getByText(en.ticket.conversation.outbound)).toBeInTheDocument();
    expect(screen.getByText(en.ticket.conversation.internal)).toBeInTheDocument();
  });

  it("marks each row with its direction for styling", async () => {
    renderPanel();
    await screen.findByText(en.ticket.conversation.inbound);

    const rows = within(screen.getByRole("list")).getAllByRole("listitem");

    expect(rows.map((r) => r.getAttribute("data-direction"))).toEqual([
      "inbound",
      "outbound",
      "internal",
    ]);
  });

  it("says outright that an internal note is not visible to the customer", async () => {
    renderPanel();
    await screen.findByText(en.ticket.conversation.internal);

    // The one sentence standing between a colleague's private remark and the
    // person it is about. Asserted on the literal string, because a renamed key
    // that silently resolves to nothing would leave the note looking like a
    // reply.
    expect(screen.getByText("Not visible to the customer")).toBeInTheDocument();
  });

  it("carries that warning in Arabic too", async () => {
    render(<ConversationPanel ticketId="01T1" onEditFailed={vi.fn()} />, { locale: "ar" });

    await screen.findByText(ar.ticket.conversation.internal);

    expect(screen.getByText(ar.ticket.internalNote.badge)).toBeInTheDocument();
  });

  it("puts the badge on the note and nowhere else", async () => {
    renderPanel();
    await screen.findByText(en.ticket.conversation.internal);

    const badges = screen.getAllByText("Not visible to the customer");

    // On a reply it would be a lie in the other direction.
    expect(badges).toHaveLength(1);
    expect(badges[0]!.closest("[data-direction]")).toHaveAttribute("data-direction", "internal");
  });

  it("has no fourth treatment", async () => {
    const { container } = renderPanel();
    await screen.findByText(en.ticket.conversation.inbound);

    const directions = [...container.querySelectorAll("[data-direction]")].map((el) =>
      el.getAttribute("data-direction"),
    );

    // No AI-draft treatment, deliberately. A machine-written suggestion that
    // looks like an agent's reply is a fourth thing the reader must learn to
    // distinguish, and the story does not build one.
    expect(new Set(directions)).toEqual(new Set(["inbound", "outbound", "internal"]));
  });
});

describe("what a message row exposes", () => {
  it("shows the sender, the time and the body", async () => {
    renderPanel();
    await screen.findByText(en.ticket.conversation.inbound);

    const row = within(screen.getByRole("list")).getAllByRole("listitem")[0]!;

    expect(within(row).getByText("Hana Yousef")).toBeInTheDocument();
    expect(within(row).getByRole("time")).toHaveAttribute("datetime", "2026-09-02T09:00:00Z");
    expect(within(row).getByText("My invoice is wrong.")).toBeInTheDocument();
  });

  it("shows a delivery chip only where there was a delivery", async () => {
    renderPanel();
    await screen.findByText(en.ticket.conversation.inbound);

    const rows = within(screen.getByRole("list")).getAllByRole("listitem");

    // An arriving customer message is not "queued", and a note is not "sent".
    expect(rows[0]!.querySelector('[data-slot="delivery-chip"]')).toBeNull();
    expect(rows[1]!.querySelector('[data-slot="delivery-chip"]')).not.toBeNull();
    expect(rows[2]!.querySelector('[data-slot="delivery-chip"]')).toBeNull();
  });

  it("lets user-written text pick its own direction", async () => {
    renderPanel();
    const body = await screen.findByText("My invoice is wrong.");

    // `dir="auto"` is the only thing that gets an Arabic body inside English
    // chrome — and an English one inside Arabic chrome — right without knowing
    // in advance which it is.
    expect(body).toHaveAttribute("dir", "auto");
  });

  it("wraps a long body instead of clipping it", async () => {
    renderPanel();
    const body = await screen.findByText("My invoice is wrong.");

    expect(body.className).toContain("whitespace-pre-wrap");
    expect(body.className).not.toContain("truncate");
  });
});

describe("a send that failed", () => {
  it("says so, and offers both ways out", async () => {
    failOne = true;
    renderPanel();

    const alert = await screen.findByRole("alert");

    // Two, because a failure has two causes: the pipeline, or what was
    // written. Retry addresses one, Edit the other.
    expect(alert).toHaveTextContent(en.ticket.conversation.failedBody);
    expect(
      within(alert).getByRole("button", { name: en.ticket.conversation.retry }),
    ).toBeInTheDocument();
    expect(
      within(alert).getByRole("button", { name: en.ticket.conversation.edit }),
    ).toBeInTheDocument();
  });

  it("re-queues on retry rather than sending a second message", async () => {
    failOne = true;
    renderPanel();

    const alert = await screen.findByRole("alert");
    await userEvent.click(within(alert).getByRole("button", { name: "Retry" }));

    await waitFor(() => expect(calls.some((c) => c.includes("/retry"))).toBe(true));

    // The agent's words appear once. A duplicate would be the customer's
    // problem, not the mail pipeline's.
    expect(calls.filter((c) => c.startsWith("POST") && !c.includes("/retry"))).toHaveLength(0);
  });

  it("marks the delivery chip so the failure survives greyscale", async () => {
    failOne = true;
    renderPanel();

    await screen.findByRole("alert");

    const chip = document.querySelector('[data-slot="delivery-chip"]')!;

    // Colour alone says nothing to a screen reader and nothing in greyscale.
    // The chip carries the word, the state, and an explanation on hover.
    expect(chip).toHaveAttribute("data-state", "failed");
    expect(chip).toHaveTextContent(en.ticket.conversation.delivery.failed);
    expect(chip).toHaveAttribute("title", en.ticket.conversation.failedBody);
  });

  it("hands the body back to the composer on edit", async () => {
    failOne = true;
    const onEdit = vi.fn();
    renderPanel(onEdit);

    const alert = await screen.findByRole("alert");
    await userEvent.click(within(alert).getByRole("button", { name: "Edit" }));

    expect(onEdit).toHaveBeenCalledWith("Looking into it now.");
  });
});
