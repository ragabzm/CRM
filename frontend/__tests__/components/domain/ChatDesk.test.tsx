import { render, screen } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { ChatDesk } from "@/components/domain/ChatDesk/ChatDesk";
import { ApiError } from "@/lib/api/errors";
import type { ChatConversationRow } from "@/lib/api/chat";
import en from "@/messages/en.json";

function conversation(overrides: Partial<ChatConversationRow> = {}): ChatConversationRow {
  return {
    id: "01JQZ0000000000000000000AA",
    state: "waiting",
    visitor_name: "Hana",
    ticket_id: "01JQZ0000000000000000000TT",
    reference: "TKT-000371",
    subject: "Is the shop open on Friday?",
    taken_by: null,
    waiting_since: "2026-09-01T10:00:00Z",
    last_activity_at: "2026-09-01T10:00:00Z",
    ...overrides,
  };
}

/**
 * The agent's side of live chat.
 *
 * What is being tested is mostly what is NOT on the row: no position, no
 * priority, no routing and nothing that hands a conversation to anybody. Each
 * of those needs to know who is available, and availability is the state this
 * story rules out by name.
 */
describe("the chat desk", () => {
  it("lists who is waiting and takes one", async () => {
    const onTake = vi.fn().mockResolvedValue(undefined);

    render(
      <ChatDesk waiting={[conversation()]} mine={[]} onTake={onTake} onOpenTicket={vi.fn()} />,
    );

    expect(screen.getByText("Is the shop open on Friday?")).toBeInTheDocument();

    await userEvent.click(screen.getByRole("button", { name: en.home.chat.take }));

    expect(onTake).toHaveBeenCalledWith("01JQZ0000000000000000000AA");
  });

  it("names the colleague who got there first", async () => {
    const onTake = vi.fn().mockRejectedValue(
      new ApiError(
        "A colleague got there first",
        {
          type: "about:blank",
          title: "A colleague got there first",
          status: 409,
          instance: "/api/v1/chat-desk/conversations/01JQZ0000000000000000000AA/take",
          code: "channels.chat_already_taken",
          trace_id: "01JQZ0000000000000000000RR",
          detail: "Nadia Salem is already answering this conversation.",
        },
        409,
      ),
    );

    render(
      <ChatDesk waiting={[conversation()]} mine={[]} onTake={onTake} onOpenTicket={vi.fn()} />,
    );

    await userEvent.click(screen.getByRole("button", { name: en.home.chat.take }));

    /*
     * Two agents watching one list will click the same row within the same
     * second — that is what a shared list does, not an edge case. A refusal
     * saying only "could not take" would send them back to a row that is gone.
     */
    expect(await screen.findByText(/Nadia Salem/)).toBeInTheDocument();
  });

  it("opens the ticket behind a conversation it already holds", async () => {
    const onOpenTicket = vi.fn();

    render(
      <ChatDesk
        waiting={[]}
        mine={[conversation({ state: "taken", taken_by: 7 })]}
        onTake={vi.fn()}
        onOpenTicket={onOpenTicket}
      />,
    );

    await userEvent.click(screen.getByRole("button", { name: en.home.chat.open }));

    // The conversation IS a ticket. Answering it is the ordinary reply path.
    expect(onOpenTicket).toHaveBeenCalledWith("01JQZ0000000000000000000TT");
  });

  it("shows no position, priority or routing on a row", () => {
    const { container } = render(
      <ChatDesk waiting={[conversation()]} mine={[]} onTake={vi.fn()} onOpenTicket={vi.fn()} />,
    );

    const row = container.querySelector("[data-slot='chat-row']");

    expect(row?.textContent).not.toMatch(/#\d|position|priority|assigned|routing/i);
  });

  it("states the polling trade rather than hiding it", () => {
    render(<ChatDesk waiting={[]} mine={[]} onTake={vi.fn()} onOpenTicket={vi.fn()} />);

    /*
     * A pane that quietly lagged would be reported as a bug. Saying there is
     * no typing indicator reads as a decision; leaving a gap reads as a
     * missing feature.
     */
    expect(screen.getByText(en.home.chat.pollNote)).toBeInTheDocument();
  });

  it("says plainly when nobody is waiting", () => {
    render(<ChatDesk waiting={[]} mine={[]} onTake={vi.fn()} onOpenTicket={vi.fn()} />);

    expect(screen.getByText(en.home.chat.nobodyWaiting)).toBeInTheDocument();
    expect(screen.getByText(en.home.chat.noneTaken)).toBeInTheDocument();
  });

  it("names a visitor who gave no name rather than leaving a gap", () => {
    render(
      <ChatDesk
        waiting={[conversation({ visitor_name: null })]}
        mine={[]}
        onTake={vi.fn()}
        onOpenTicket={vi.fn()}
      />,
    );

    expect(screen.getByText(en.home.chat.anonymous)).toBeInTheDocument();
  });
});
