import { render, screen } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { AiSuggestionPanel } from "@/components/domain/AiSuggestionPanel/AiSuggestionPanel";
import { CategoryProposal } from "@/components/domain/CategoryProposal/CategoryProposal";
import en from "@/messages/en.json";

const assistSummary = vi.fn();
const assistReply = vi.fn();
const assistArticles = vi.fn();
const assistCategory = vi.fn();

vi.mock("@/lib/api/assist", () => ({
  assistSummary: (...a: unknown[]) => assistSummary(...a),
  assistReply: (...a: unknown[]) => assistReply(...a),
  assistArticles: (...a: unknown[]) => assistArticles(...a),
  assistCategory: (...a: unknown[]) => assistCategory(...a),
}));

const TICKET = "01JQZ0000000000000000000TT";

/**
 * The machine drafts, summarises and suggests. The agent decides.
 *
 * Most of these test the second half. Nothing here applies anything, nothing
 * sends, nothing is fetched until somebody asks, and every artefact says what
 * it is.
 */
describe("the assistant panel", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    assistSummary.mockResolvedValue("They were charged twice.");
    assistReply.mockResolvedValue(["Sorry about that — refunded."]);
    assistArticles.mockResolvedValue([
      {
        id: "a1",
        title: "Refunding a duplicate charge",
        type: "faq",
        internal_only: false,
        served_locale: "en",
      },
    ]);
  });

  it("asks for nothing until somebody asks", () => {
    render(<AiSuggestionPanel ticketId={TICKET} onUseDraft={vi.fn()} onInsertArticle={vi.fn()} />);

    /*
     * No message-count threshold, no automatic generation and no setting that
     * would create one. A panel that summarised every ticket on open would be
     * a request to a provider nobody asked for, on every ticket, for ever.
     */
    expect(assistSummary).not.toHaveBeenCalled();
    expect(assistReply).not.toHaveBeenCalled();
    expect(assistArticles).not.toHaveBeenCalled();
  });

  it("summarises on demand and labels what it produced", async () => {
    render(<AiSuggestionPanel ticketId={TICKET} onUseDraft={vi.fn()} onInsertArticle={vi.fn()} />);

    await userEvent.click(screen.getByText(en.ticket.assist.summarise));

    expect(await screen.findByText("They were charged twice.")).toBeInTheDocument();
    // Nothing generated reaches a person without saying what it is.
    expect(screen.getByText(en.ai.label)).toBeInTheDocument();
  });

  it("puts a draft in the composer rather than sending it", async () => {
    const onUseDraft = vi.fn();

    render(
      <AiSuggestionPanel ticketId={TICKET} onUseDraft={onUseDraft} onInsertArticle={vi.fn()} />,
    );

    await userEvent.click(screen.getByText(en.ticket.assist.draft));
    await userEvent.click(await screen.findByText(en.ticket.assist.useDraft));

    /*
     * "Put this in the composer", not "Send". The wording is the guarantee:
     * an agent reads it, edits it, and sends it themselves.
     */
    expect(onUseDraft).toHaveBeenCalledWith("Sorry about that — refunded.");
  });

  it("offers no way to send anything", async () => {
    const { container } = render(
      <AiSuggestionPanel ticketId={TICKET} onUseDraft={vi.fn()} onInsertArticle={vi.fn()} />,
    );

    await userEvent.click(screen.getByText(en.ticket.assist.draft));
    await screen.findByText(en.ticket.assist.useDraft);

    // No send, no auto-send, no accept-and-commit. There is no path from this
    // panel to a customer.
    expect(container.textContent).not.toMatch(/\bsend\b|auto|automatic/i);
  });

  it("marks an internal article as not for the customer", async () => {
    assistArticles.mockResolvedValue([
      {
        id: "a2",
        title: "How we handle duplicates",
        type: "guide",
        internal_only: true,
        served_locale: "en",
      },
    ]);

    render(<AiSuggestionPanel ticketId={TICKET} onUseDraft={vi.fn()} onInsertArticle={vi.fn()} />);

    await userEvent.click(screen.getByText(en.ticket.assist.findArticles));

    /*
     * An internal article is in scope for an agent and must never be pasted to
     * a customer — and the one thing standing between those is the agent
     * knowing which it is.
     */
    expect(await screen.findByText(en.ticket.assist.internalOnly)).toBeInTheDocument();
  });

  it("renders nothing at all when the provider says nothing", async () => {
    assistSummary.mockResolvedValue(null);
    assistReply.mockResolvedValue([]);
    assistArticles.mockResolvedValue([]);

    const { container } = render(
      <AiSuggestionPanel ticketId={TICKET} onUseDraft={vi.fn()} onInsertArticle={vi.fn()} />,
    );

    await userEvent.click(screen.getByText(en.ticket.assist.summarise));

    // Absence, not an error. No toast, no retry control, no empty panel with
    // a spinner in it.
    expect(container.querySelector("[data-slot='assist-summary-text']")).toBeNull();
    expect(container.textContent).not.toMatch(/error|failed|retry/i);
  });

  it("stays silent when the call fails", async () => {
    assistSummary.mockRejectedValue(new Error("provider down"));

    const { container } = render(
      <AiSuggestionPanel ticketId={TICKET} onUseDraft={vi.fn()} onInsertArticle={vi.fn()} />,
    );

    await userEvent.click(screen.getByText(en.ticket.assist.summarise));

    expect(container.textContent).not.toMatch(/error|failed|retry/i);
  });
});

describe("the category proposal", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    assistCategory.mockResolvedValue({ category_id: 3, name: "Billing" });
  });

  it("shows the proposal beside the field, labelled", async () => {
    render(<CategoryProposal ticketId={TICKET} current={null} onConfirm={vi.fn()} />);

    expect(await screen.findByText("Billing")).toBeInTheDocument();
    expect(screen.getByText(en.ai.label)).toBeInTheDocument();
  });

  it("changes nothing until somebody confirms", async () => {
    const onConfirm = vi.fn();

    render(<CategoryProposal ticketId={TICKET} current={null} onConfirm={onConfirm} />);

    await screen.findByText("Billing");

    // A pre-filled field is an application. Until this button is pressed the
    // ticket is untouched.
    expect(onConfirm).not.toHaveBeenCalled();

    await userEvent.click(screen.getByText(en.ticket.assist.confirmCategory));
    expect(onConfirm).toHaveBeenCalledWith(3);
  });

  it("says nothing when the ticket already has that category", async () => {
    const { container } = render(
      <CategoryProposal ticketId={TICKET} current={3} onConfirm={vi.fn()} />,
    );

    // A suggestion to change something to what it already is reads as the
    // product not knowing what it is looking at.
    expect(container.querySelector("[data-slot='proposed-category']")).toBeNull();
  });

  it("shows no confidence score, slider or threshold", async () => {
    const { container } = render(
      <CategoryProposal ticketId={TICKET} current={null} onConfirm={vi.fn()} />,
    );

    await screen.findByText("Billing");

    /*
     * Because nothing is applied automatically there is nothing to threshold.
     * A confidence number only exists to decide when to skip the human, and
     * the human is never skipped.
     */
    expect(container.textContent).not.toMatch(/\d+\s*%|confidence/i);
    expect(container.querySelectorAll("input[type='range']")).toHaveLength(0);
  });
});
