import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { fireEvent } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const push = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push, replace: vi.fn(), refresh: vi.fn() }),
  usePathname: () => "/portal/requests",
  useSearchParams: () => new URLSearchParams(),
}));

import { PortalNewRequest } from "@/components/screens/portal/PortalNewRequest";
import { PortalRequestDetail } from "@/components/screens/portal/PortalRequestDetail";
import { PortalRequestList } from "@/components/screens/portal/PortalRequestList";
import en from "@/messages/en.json";

const SUMMARY = {
  id: "01R1",
  reference: "TKT-000042",
  subject: "My invoice is wrong",
  status: "pending" as const,
  created_at: "2026-09-01T08:00:00Z",
  updated_at: "2026-09-02T08:00:00Z",
};

const DETAIL = {
  ...SUMMARY,
  description: "I was charged twice in August.",
  messages: [
    {
      id: "01M1",
      from: "support" as const,
      body: "Could you send us the invoice number?",
      sent_at: "2026-09-02T09:00:00Z",
      attachments: [],
    },
  ],
};

let list: unknown[] = [SUMMARY];
let detail: Record<string, unknown> = DETAIL;
let status = 200;
let problem: Record<string, unknown> | null = null;
let posts: Array<{ url: string; body: Record<string, unknown> }> = [];

function json(body: unknown, code = 200) {
  return new Response(JSON.stringify(body), {
    status: code,
    headers: { "Content-Type": code >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  list = [SUMMARY];
  detail = DETAIL;
  status = 200;
  problem = null;
  posts = [];
  push.mockClear();

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);

      if (url.includes("/sanctum/csrf-cookie")) return new Response(null, { status: 204 });

      if ((init?.method ?? "GET") === "POST") {
        posts.push({ url, body: JSON.parse(String(init?.body ?? "{}")) });

        if (status !== 200) return json(problem ?? { status, code: "x" }, status);

        return json(
          detail,
          url.includes("/requests") && !url.includes("/replies") && !url.includes("/reopen")
            ? 201
            : 200,
        );
      }

      if (status === 404) return json({ status: 404, code: "portal.request_not_found" }, 404);
      if (/requests\/[^/?]+$/.test(url)) return json(detail);

      return json({ data: list });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

describe("the list of a customer's requests", () => {
  it("says whose turn it is, in their words", async () => {
    render(<PortalRequestList onOpen={vi.fn()} />);

    /*
     * "Waiting for you", not "pending". A status is only useful to a customer
     * if it tells them whether to do something.
     */
    expect(await screen.findByText(en.portal.requests.status.pending)).toBeInTheDocument();
  });

  it("shows the reference so it can be read out over the phone", async () => {
    render(<PortalRequestList onOpen={vi.fn()} />);

    const reference = await screen.findByText("TKT-000042");

    expect(reference.getAttribute("dir")).toBe("ltr");
  });

  it("opens the request that was tapped", async () => {
    const onOpen = vi.fn();
    render(<PortalRequestList onOpen={onOpen} />);

    await userEvent.click(await screen.findByRole("button", { name: /My invoice is wrong/ }));

    expect(onOpen).toHaveBeenCalledWith("01R1");
  });

  it("says nothing has been asked rather than showing a bare list", async () => {
    list = [];
    render(<PortalRequestList onOpen={vi.fn()} />);

    expect(await screen.findByText(en.portal.requests.empty)).toBeInTheDocument();
  });

  it("wraps a long subject instead of clipping it at 320px", async () => {
    render(<PortalRequestList onOpen={vi.fn()} />);

    const subject = await screen.findByText("My invoice is wrong");

    // A truncated subject hides the words that told them which request it is.
    expect(subject.className).toContain("break-words");
    expect(subject.className).not.toContain("truncate");
  });
});

describe("one request", () => {
  it("puts the original question at the top of the thread", async () => {
    render(<PortalRequestDetail requestId="01R1" />);

    const turns = await screen.findAllByRole("listitem");

    // The question is the first thing said, and belongs in the conversation
    // rather than in a separate box above it.
    expect(within(turns[0]!).getByText("I was charged twice in August.")).toBeInTheDocument();
  });

  it("says which side spoke, without naming an agent", async () => {
    render(<PortalRequestDetail requestId="01R1" />);

    await screen.findByText("Could you send us the invoice number?");

    const turns = screen.getAllByRole("listitem");

    /*
     * "Support", not a name. Who exactly is the desk's business, and a name
     * here follows that agent into every later conversation.
     */
    expect(turns[1]).toHaveAttribute("data-from", "support");
    expect(within(turns[1]!).getByText(en.portal.detail.support)).toBeInTheDocument();
  });

  it("sends a reply", async () => {
    render(<PortalRequestDetail requestId="01R1" />);
    await screen.findByText("Could you send us the invoice number?");

    fireEvent.change(screen.getByRole("textbox"), { target: { value: "It is INV-9912." } });
    await userEvent.click(screen.getByRole("button", { name: en.portal.detail.send }));

    await waitFor(() => expect(posts).toHaveLength(1));
    expect(posts[0]!.body.body).toBe("It is INV-9912.");
  });

  it("keeps what was typed when a reply fails", async () => {
    render(<PortalRequestDetail requestId="01R1" />);
    await screen.findByText("Could you send us the invoice number?");

    status = 500;
    fireEvent.change(screen.getByRole("textbox"), { target: { value: "It is INV-9912." } });
    await userEvent.click(screen.getByRole("button", { name: en.portal.detail.send }));

    // The failure was not theirs, and their words are the one thing that
    // cannot be recovered from anywhere else.
    expect(await screen.findByRole("alert")).toHaveTextContent(en.portal.detail.sendError);
    expect(screen.getByRole("textbox")).toHaveValue("It is INV-9912.");
  });

  it("offers a reopen on a closed request instead of a reply box", async () => {
    detail = { ...DETAIL, status: "closed" };
    render(<PortalRequestDetail requestId="01R1" />);

    expect(
      await screen.findByRole("button", { name: en.portal.detail.reopen }),
    ).toBeInTheDocument();
    expect(screen.queryByRole("textbox")).toBeNull();
  });

  it("offers a way forward when the reopen window has passed", async () => {
    detail = { ...DETAIL, status: "closed" };
    render(<PortalRequestDetail requestId="01R1" />);

    status = 409;
    problem = {
      status: 409,
      code: "tickets.reopen_window_expired",
      new_request_url: "/portal/requests/new?from=01R1",
    };

    await userEvent.click(await screen.findByRole("button", { name: en.portal.detail.reopen }));

    /*
     * "No" on its own is a dead end — and the next thing somebody does is email
     * support about not being able to use support.
     */
    const alert = await screen.findByRole("alert");

    expect(alert).toHaveTextContent(en.portal.detail.reopenExpired);
    expect(within(alert).getByRole("link", { name: en.portal.detail.startNew })).toHaveAttribute(
      "href",
      "/portal/requests/new?from=01R1",
    );
  });

  it("says a missing request is missing rather than showing an empty page", async () => {
    status = 404;
    render(<PortalRequestDetail requestId="01R1" />);

    expect(await screen.findByText(en.portal.detail.notFound)).toBeInTheDocument();
  });
});

describe("asking something new", () => {
  it("sends the subject and description", async () => {
    render(<PortalNewRequest customerId="01C1" onSubmitted={vi.fn()} />);

    fireEvent.change(screen.getByLabelText(en.portal.new.subject), {
      target: { value: "My invoice is wrong" },
    });
    fireEvent.change(screen.getByRole("textbox", { name: en.portal.new.description }), {
      target: { value: "Charged twice." },
    });

    await userEvent.click(screen.getByRole("button", { name: en.portal.new.submit }));

    await waitFor(() => expect(posts).toHaveLength(1));
    expect(posts[0]!.body.subject).toBe("My invoice is wrong");
  });

  it("does not require a category", async () => {
    render(<PortalNewRequest customerId="01C1" onSubmitted={vi.fn()} />);

    // A required dropdown somebody cannot confidently answer is where a
    // request gets abandoned.
    expect(screen.queryByRole("combobox")).toBeNull();
  });

  it("offers the camera as well as the photo library", () => {
    render(<PortalNewRequest customerId="01C1" onSubmitted={vi.fn()} />);

    const picker = document.querySelector('[data-slot="attachment-picker"] input[type="file"]');

    /*
     * Most of what a customer attaches is a photo of a screen or a receipt,
     * taken there and then. Making them leave the form, take it, and come back
     * is where the request gets abandoned.
     */
    expect(picker).toHaveAttribute("capture", "environment");
    expect(picker?.getAttribute("accept")).toContain("image/*");
  });

  it("takes the person to the request they just made", async () => {
    const onSubmitted = vi.fn();
    render(<PortalNewRequest customerId="01C1" onSubmitted={onSubmitted} />);

    fireEvent.change(screen.getByLabelText(en.portal.new.subject), { target: { value: "x" } });
    await userEvent.click(screen.getByRole("button", { name: en.portal.new.submit }));

    // The confirmation somebody wants is seeing the thing exist.
    await waitFor(() => expect(onSubmitted).toHaveBeenCalledWith("01R1"));
  });
});
