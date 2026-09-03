import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { fireEvent } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/admin/email",
}));

import { EmailTestSend } from "@/components/domain/EmailTestSend/EmailTestSend";
import en from "@/messages/en.json";

/**
 * Proving the channel works, and saying what went wrong when it does not.
 *
 * The failure copy is the point. "Sending failed" is useless: an administrator
 * cannot tell an expired API key from a blocked port from a rejected sender
 * domain. The provider already diagnosed it.
 */

let failure: { detail: string; retryable: boolean } | null = null;
let posted: Array<Record<string, unknown>> = [];

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  failure = null;
  posted = [];

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);

      if (url.includes("/sanctum/csrf-cookie")) return new Response(null, { status: 204 });

      posted.push(JSON.parse(String(init?.body ?? "{}")));

      if (failure !== null) {
        return json(
          {
            status: 502,
            code: "email.test_send_failed",
            title: "The test email could not be sent",
            detail: failure.detail,
            retryable: failure.retryable,
          },
          502,
        );
      }

      return json({ status: "sent", provider: "smtp", sent_to: "admin@example.test" });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const send = async () => {
  await userEvent.click(screen.getByRole("button", { name: en.admin.email.sendTest }));
};

describe("the email test send", () => {
  it("says where it went", async () => {
    render(<EmailTestSend />);

    await send();

    expect(await screen.findByRole("status")).toHaveTextContent("admin@example.test");
  });

  it("sends to the administrator by default", async () => {
    render(<EmailTestSend />);

    await send();

    // A test that emails somebody else is a test whose result they cannot see.
    await waitFor(() => expect(posted).toHaveLength(1));
    expect(posted[0]).toEqual({});
  });

  it("sends to an address when one is given", async () => {
    render(<EmailTestSend />);

    fireEvent.change(screen.getByLabelText(en.admin.email.sendTestTo), {
      target: { value: "someone@customer.test" },
    });

    await send();

    // Verifying delivery to a customer domain is a real thing administrators
    // need to do.
    await waitFor(() => expect(posted[0]).toEqual({ to: "someone@customer.test" }));
  });

  it("shows the provider's own words when it fails", async () => {
    failure = { detail: "535 Authentication credentials invalid", retryable: false };
    render(<EmailTestSend />);

    await send();

    const alert = await screen.findByRole("alert");

    // The difference between a five-minute fix and a support ticket.
    expect(alert).toHaveTextContent("535 Authentication credentials invalid");
  });

  it("says whether trying again could help", async () => {
    failure = { detail: "Connection reset", retryable: true };
    render(<EmailTestSend />);

    await send();

    // The administrator's actual next decision.
    expect(await screen.findByRole("alert")).toHaveTextContent(en.admin.email.testRetryable);
  });

  it("says when trying again will not help", async () => {
    failure = { detail: "535 auth failed", retryable: false };
    render(<EmailTestSend />);

    await send();

    expect(await screen.findByRole("alert")).toHaveTextContent(en.admin.email.testPermanent);
  });

  it("clears a previous failure when a later send works", async () => {
    failure = { detail: "Connection reset", retryable: true };
    render(<EmailTestSend />);

    await send();
    await screen.findByRole("alert");

    failure = null;
    await send();

    await waitFor(() => expect(screen.queryByRole("alert")).toBeNull());
    expect(screen.getByRole("status")).toBeInTheDocument();
  });

  it("wraps a long provider message instead of clipping it", async () => {
    failure = { detail: "A ".repeat(200) + "very long provider diagnosis", retryable: false };
    render(<EmailTestSend />);

    await send();

    const alert = await screen.findByRole("alert");

    // The useful part of a provider error is often at the end.
    expect(within(alert).getByText(/very long provider diagnosis/)).toHaveClass("break-words");
  });
});
