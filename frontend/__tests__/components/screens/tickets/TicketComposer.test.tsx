import { render, screen, waitFor, withIntl } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { fireEvent } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/tickets/01T1",
}));

import { TicketComposer } from "@/components/domain/TicketComposer/TicketComposer";

const QUICK_REPLIES = [
  { id: 1, title: "Ask for the invoice number", body: "Could you send us the invoice number?" },
];

let posts: Array<{ url: string; body: Record<string, unknown> }> = [];
let sendFails = false;

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  posts = [];
  sendFails = false;
  localStorage.clear();

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = init?.method ?? "GET";

      if (url.includes("/quick-replies")) return json({ data: QUICK_REPLIES });
      if (url.includes("/sanctum/csrf-cookie")) return new Response(null, { status: 204 });

      if (method === "POST" && url.includes("/messages")) {
        if (sendFails) return json({ status: 500, code: "platform.internal_error" }, 500);

        posts.push({ url, body: JSON.parse(String(init?.body ?? "{}")) });

        return json({ id: "01M9" }, 201);
      }

      return json({ data: [] });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const renderComposer = (props: Partial<React.ComponentProps<typeof TicketComposer>> = {}) =>
  render(<TicketComposer ticketId="01T1" onSent={vi.fn()} {...props} />);

const box = () => screen.getByRole("textbox");

describe("the composer's draft", () => {
  it("keeps what was typed but not sent", async () => {
    renderComposer();

    fireEvent.change(box(), { target: { value: "Half a sentence" } });

    await waitFor(() =>
      expect(JSON.parse(localStorage.getItem("ticket.draft.01T1") ?? "{}").body).toBe(
        "Half a sentence",
      ),
    );
  });

  it("puts it back on the next mount", async () => {
    localStorage.setItem(
      "ticket.draft.01T1",
      JSON.stringify({ body: "Three sentences in", type: "reply", attachmentIds: [] }),
    );

    renderComposer();

    // A session that lapses or a colleague's change forcing a reload is not the
    // agent's doing, and should not cost them the paragraph they were writing.
    await waitFor(() => expect(box()).toHaveValue("Three sentences in"));
  });

  it("keeps each ticket's draft separate", async () => {
    renderComposer();
    fireEvent.change(box(), { target: { value: "For ticket one" } });

    await waitFor(() => expect(localStorage.getItem("ticket.draft.01T1")).not.toBeNull());
    expect(localStorage.getItem("ticket.draft.01T2")).toBeNull();
  });

  it("clears it only after the server confirms", async () => {
    renderComposer();

    fireEvent.change(box(), { target: { value: "Ready to send" } });
    await waitFor(() => expect(localStorage.getItem("ticket.draft.01T1")).not.toBeNull());

    await userEvent.click(screen.getByRole("button", { name: "Send" }));

    await waitFor(() => expect(localStorage.getItem("ticket.draft.01T1")).toBeNull());
  });

  it("keeps the draft when the send fails", async () => {
    sendFails = true;
    renderComposer();

    fireEvent.change(box(), { target: { value: "This will not go" } });
    await userEvent.click(screen.getByRole("button", { name: "Send" }));

    // The failure was not the agent's, and their words are the one thing that
    // cannot be recovered from anywhere else.
    await waitFor(() => expect(screen.getByRole("alert")).toBeInTheDocument());
    expect(box()).toHaveValue("This will not go");
    expect(localStorage.getItem("ticket.draft.01T1")).not.toBeNull();
  });

  it("survives storage being unavailable entirely", async () => {
    // A private window, or a browser set to block site data, throws on write.
    vi.spyOn(Storage.prototype, "setItem").mockImplementation(() => {
      throw new Error("blocked");
    });

    renderComposer();

    // A composer that crashes because it could not save a draft has destroyed
    // the very thing it was protecting.
    expect(() => fireEvent.change(box(), { target: { value: "Still typeable" } })).not.toThrow();
    expect(box()).toHaveValue("Still typeable");

    vi.restoreAllMocks();
  });

  it("ignores a corrupt stored draft", async () => {
    localStorage.setItem("ticket.draft.01T1", "{not json");

    renderComposer();

    await waitFor(() => expect(box()).toHaveValue(""));
  });
});

describe("quick replies", () => {
  it("inserts the snippet as written, without substitution", async () => {
    renderComposer();

    await userEvent.click(await screen.findByRole("button", { name: "Quick replies" }));
    await userEvent.click(await screen.findByRole("menuitem", { name: /invoice number/ }));

    // Verbatim. A composer that silently expanded `{{customer_name}}` would
    // eventually send "Dear ," to somebody, and the agent who pressed Send
    // would have had no way to see it coming.
    await waitFor(() => expect(box()).toHaveValue("Could you send us the invoice number?"));
  });

  it("does not send", async () => {
    renderComposer();

    await userEvent.click(await screen.findByRole("button", { name: "Quick replies" }));
    await userEvent.click(await screen.findByRole("menuitem", { name: /invoice number/ }));

    await waitFor(() => expect(box()).not.toHaveValue(""));
    expect(posts).toHaveLength(0);
  });

  it("inserts at the caret rather than replacing what was typed", async () => {
    renderComposer();

    fireEvent.change(box(), { target: { value: "Hello. " } });
    (box() as HTMLTextAreaElement).setSelectionRange(7, 7);

    await userEvent.click(await screen.findByRole("button", { name: "Quick replies" }));
    await userEvent.click(await screen.findByRole("menuitem", { name: /invoice number/ }));

    // Losing an agent's own opening line to a template would make the picker
    // something to avoid.
    await waitFor(() => expect(box()).toHaveValue("Hello. Could you send us the invoice number?"));
  });

  it("still composes when there are none", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => json({ data: [] })),
    );

    renderComposer();

    // Checked before the menu opens: Radix marks the rest of the page
    // aria-hidden while it is up, so the textbox is legitimately unreachable
    // then — which is the menu working, not the composer broken.
    expect(box()).toBeEnabled();

    await userEvent.click(await screen.findByRole("button", { name: "Quick replies" }));

    // An empty picker is a missing convenience, not a missing job.
    expect(await screen.findByText("No quick replies yet")).toBeInTheDocument();
  });
});

describe("sending", () => {
  it("never carries a version", async () => {
    renderComposer();

    fireEvent.change(box(), { target: { value: "A reply" } });
    await userEvent.click(screen.getByRole("button", { name: "Send" }));

    await waitFor(() => expect(posts).toHaveLength(1));

    /*
     * An append, not a change. Two colleagues writing different replies have
     * not conflicted — both belong in the thread. Requiring a version would
     * refuse a reply whenever someone changed the priority a moment earlier.
     */
    expect(posts[0]!.body).not.toHaveProperty("version");
    expect(posts[0]!.body).not.toHaveProperty("If-Match");
  });

  it("sends a reply as outbound", async () => {
    renderComposer();

    fireEvent.change(box(), { target: { value: "A reply" } });
    await userEvent.click(screen.getByRole("button", { name: "Send" }));

    await waitFor(() => expect(posts[0]!.body.direction).toBe("outbound"));
  });

  it("sends a note as internal, never outbound", async () => {
    renderComposer();

    await userEvent.click(screen.getByRole("button", { name: "Internal note" }));
    fireEvent.change(box(), { target: { value: "Check the billing run" } });
    await userEvent.click(screen.getByRole("button", { name: "Save note" }));

    // The direction is the only thing keeping this out of the customer's inbox.
    await waitFor(() => expect(posts[0]!.body.direction).toBe("internal"));
  });

  it("warns before the note is written, not after it is sent", async () => {
    renderComposer();

    await userEvent.click(screen.getByRole("button", { name: "Internal note" }));

    expect(screen.getByText("Not visible to the customer")).toBeInTheDocument();
  });

  it("refuses to send an empty message", async () => {
    renderComposer();

    expect(screen.getByRole("button", { name: "Send" })).toBeDisabled();
  });

  it("re-hydrates from a failed message when asked to edit it", async () => {
    const { rerender } = renderComposer();

    // Re-wrapped: a bare rerender drops the intl provider the first render
    // installed, and every label would come back as its key.
    rerender(
      withIntl(<TicketComposer ticketId="01T1" onSent={vi.fn()} seedBody="The one that failed" />),
    );

    await waitFor(() => expect(box()).toHaveValue("The one that failed"));
  });
});
