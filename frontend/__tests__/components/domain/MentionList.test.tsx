import { render, screen } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { MentionList } from "@/components/domain/MentionList/MentionList";
import type { PersonalMention } from "@/lib/api/personal";
import en from "@/messages/en.json";

function mention(overrides: Partial<PersonalMention> = {}): PersonalMention {
  return {
    id: "01JQZ0000000000000000000AA",
    message_id: "01JQZ0000000000000000000MM",
    ticket_id: "01JQZ0000000000000000000TT",
    reference: "TKT-000371",
    subject: "Order cancelled but still charged",
    author_name: "Nadia Salem",
    excerpt: "Omar, can you sanity-check the timeline?",
    read_at: null,
    mentioned_at: "2026-09-01T10:00:00Z",
    ...overrides,
  };
}

describe("mentions on Home", () => {
  it("says who named you, where, and what they said", () => {
    render(<MentionList mentions={[mention()]} onOpen={vi.fn()} onMarkRead={vi.fn()} />);

    expect(screen.getByText("Nadia Salem")).toBeInTheDocument();
    expect(screen.getByText("TKT-000371")).toBeInTheDocument();
    expect(screen.getByText(/sanity-check the timeline/)).toBeInTheDocument();
  });

  it("opens the ticket at the note, not the top of the thread", async () => {
    const onOpen = vi.fn();

    render(<MentionList mentions={[mention()]} onOpen={onOpen} onMarkRead={vi.fn()} />);

    await userEvent.click(screen.getByRole("button", { name: /TKT-000371/ }));

    /*
     * The whole content of a mention is "come and read this sentence".
     * Landing at the top of a long conversation asks somebody to search for it.
     */
    expect(onOpen).toHaveBeenCalledWith("01JQZ0000000000000000000TT", "01JQZ0000000000000000000MM");
  });

  it("marks one as read", async () => {
    const onMarkRead = vi.fn().mockResolvedValue(undefined);

    render(<MentionList mentions={[mention()]} onOpen={vi.fn()} onMarkRead={onMarkRead} />);

    await userEvent.click(screen.getByRole("button", { name: en.home.mentions.markRead }));

    expect(onMarkRead).toHaveBeenCalledWith("01JQZ0000000000000000000AA");
  });

  it("still lists a mention that has been read", () => {
    const { container } = render(
      <MentionList
        mentions={[mention({ read_at: "2026-09-02T09:00:00Z" })]}
        onOpen={vi.fn()}
        onMarkRead={vi.fn()}
      />,
    );

    // Read is not deleted — the note still named them, and the row still opens
    // the conversation.
    expect(container.querySelector("[data-slot='mention-row']")).toHaveAttribute(
      "data-read",
      "true",
    );
    expect(screen.queryByRole("button", { name: en.home.mentions.markRead })).toBeNull();
  });

  it("offers nothing that would follow the ticket", () => {
    const { container } = render(
      <MentionList mentions={[mention()]} onOpen={vi.fn()} onMarkRead={vi.fn()} />,
    );

    /*
     * A mention notifies and does nothing else. A "follow" control here would
     * be the first half of a followers list, a subscription and a visibility
     * grant nobody remembers agreeing to.
     */
    expect(container.textContent).not.toMatch(/follow|subscribe|watch/i);
  });

  it("says so plainly when nobody has asked for you", () => {
    render(<MentionList mentions={[]} onOpen={vi.fn()} onMarkRead={vi.fn()} />);

    expect(screen.getByText(en.home.mentions.empty)).toBeInTheDocument();
  });
});
