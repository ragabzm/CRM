import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import { fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/tickets/01T1",
}));

import { TicketComposer } from "@/components/domain/TicketComposer/TicketComposer";
import en from "@/messages/en.json";

/**
 * Finding an article without losing the reply you were writing.
 *
 * The first test is the one that matters. An agent three sentences into a
 * reply who searches for an article and comes back to an empty box will not
 * use the panel a second time — and the whole value of putting search inside
 * the ticket was that they would.
 */

const HITS = [
  {
    id: "01ARTICLEREFUNDS000000000A",
    title: "Refund policy",
    type: "faq" as const,
    category_id: 1,
    status: "published" as const,
    internal_only: false,
    served_locale: "en" as const,
    available_locales: ["en" as const],
  },
  {
    id: "01ARTICLEINTERNAL00000000B",
    title: "Refunds — internal",
    type: "solution" as const,
    category_id: 1,
    status: "published" as const,
    internal_only: true,
    served_locale: "en" as const,
    available_locales: ["en" as const],
  },
];

let hits = HITS;

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  hits = HITS;
  localStorage.clear();

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);

      if (url.includes("/knowledge/search")) return json({ data: hits });
      if (url.includes("/quick-replies")) return json({ data: [] });
      if (url.includes("/sanctum/csrf-cookie")) return new Response(null, { status: 204 });

      return json({ data: [] });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

describe("finding an article from inside a ticket", () => {
  it("keeps the half-written reply while searching and inserting", async () => {
    render(<TicketComposer ticketId="01T1" onSent={vi.fn()} />);

    const body = screen.getByRole("textbox", { name: en.ticket.composer.placeholder });
    fireEvent.change(body, { target: { value: "Sorry about that — " } });

    fireEvent.change(screen.getByLabelText(en.knowledge.finder.label), {
      target: { value: "refund" },
    });

    await waitFor(() => expect(screen.getByText("Refund policy")).toBeInTheDocument());

    // Still there, untouched, while the results are on screen.
    expect((body as HTMLTextAreaElement).value).toBe("Sorry about that — ");

    await userEvent.click(screen.getByText("Refund policy"));

    /*
     * And the link is APPENDED to what they wrote, in one action — no dialog,
     * no copy-and-paste, and nothing overwritten.
     */
    expect((body as HTMLTextAreaElement).value).toContain("Sorry about that — ");
    expect((body as HTMLTextAreaElement).value).toContain("[Refund policy](/help/articles/");
  });

  it("keys the link on the article id, never on its title", async () => {
    render(<TicketComposer ticketId="01T1" onSent={vi.fn()} />);

    fireEvent.change(screen.getByLabelText(en.knowledge.finder.label), {
      target: { value: "refund" },
    });

    await waitFor(() => expect(screen.getByText("Refund policy")).toBeInTheDocument());
    await userEvent.click(screen.getByText("Refund policy"));

    const body = screen.getByRole("textbox", {
      name: en.ticket.composer.placeholder,
    }) as HTMLTextAreaElement;

    /*
     * A retitled article's link in a two-year-old reply still resolves,
     * because nothing in the URL depends on what it is called today.
     */
    expect(body.value).toContain("/help/articles/01ARTICLEREFUNDS000000000A");
    expect(body.value).not.toContain("refund-policy");
  });

  it("warns that an internal article is internal, before it is pasted to a customer", async () => {
    const { container } = render(<TicketComposer ticketId="01T1" onSent={vi.fn()} />);

    fireEvent.change(screen.getByLabelText(en.knowledge.finder.label), {
      target: { value: "refund" },
    });

    await waitFor(() => expect(screen.getByText("Refunds — internal")).toBeInTheDocument());

    /*
     * An agent pasting a link to an INTERNAL article into a customer reply
     * would be sending a link that answers "not available" — and would not
     * find out until the customer told them.
     */
    const markers = container.querySelectorAll("[data-slot='internal-marker']");

    expect(markers).toHaveLength(1);
    expect(markers[0]!.textContent).toBe(en.knowledge.finder.internal);
  });

  it("says an empty result is empty, and what to do about it", async () => {
    hits = [];

    render(<TicketComposer ticketId="01T1" onSent={vi.fn()} />);

    fireEvent.change(screen.getByLabelText(en.knowledge.finder.label), {
      target: { value: "somethingnobodywroteabout" },
    });

    // UX-06: unmistakable, and it offers the way on rather than an empty box.
    await waitFor(() =>
      expect(screen.getByText(en.knowledge.finder.noResultsHint)).toBeInTheDocument(),
    );
  });

  it("searches nothing until there is something to search for", async () => {
    render(<TicketComposer ticketId="01T1" onSent={vi.fn()} />);

    const calls = () =>
      (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls.filter((c) =>
        String(c[0]).includes("/knowledge/search"),
      );

    expect(calls()).toHaveLength(0);

    fireEvent.change(screen.getByLabelText(en.knowledge.finder.label), {
      target: { value: "   " },
    });

    // Whitespace is not a query. Sending it would ask the busiest endpoint in
    // the product for everything.
    await new Promise((resolve) => setTimeout(resolve, 400));
    expect(calls()).toHaveLength(0);
  });
});
