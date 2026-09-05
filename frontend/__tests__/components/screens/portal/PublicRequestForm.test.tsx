import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import { fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { PublicRequestFormScreen } from "@/components/screens/portal/PublicRequestFormScreen";
import en from "@/messages/en.json";

/**
 * The public form, from the point of view of somebody with no account.
 *
 * Two of these tests are about things a person never sees — the honeypot and
 * the fill-time stamp. They are here because both are invisible by design, and
 * a protection nobody can see is a protection that silently stops working.
 */

let submissions: Array<Record<string, unknown>> = [];
let submitStatus = 201;
let submitDetail: string | null = null;

function json(body: unknown, code = 200) {
  return new Response(JSON.stringify(body), {
    status: code,
    headers: { "Content-Type": code >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  submissions = [];
  submitStatus = 201;
  submitDetail = null;

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);

      if (url.includes("/inbound/web-form/session")) {
        return json(
          {
            session_token: "01ABCDEFGHJKMNPQRSTVWXYZ00",
            expires_at: new Date(Date.now() + 3_600_000).toISOString(),
            categories: [
              { id: 1, name: "Billing" },
              { id: 2, name: "Account" },
            ],
          },
          201,
        );
      }

      submissions.push(JSON.parse(String(init?.body ?? "{}")));

      if (submitStatus !== 201) {
        return json(
          { status: submitStatus, code: "channels.web_form_too_fast", detail: submitDetail },
          submitStatus,
        );
      }

      return json({ status: "accepted", reference: "TKT-000042" }, 201);
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

async function fillIn() {
  fireEvent.change(screen.getByLabelText(en.webForm.name), { target: { value: "Hana Yousef" } });
  fireEvent.change(screen.getByLabelText(en.webForm.contact), {
    target: { value: "hana@example.test" },
  });
  fireEvent.change(screen.getByLabelText(en.webForm.subject), {
    target: { value: "My invoice is wrong" },
  });
  fireEvent.change(screen.getByLabelText(en.webForm.message), {
    target: { value: "I was charged twice." },
  });

  await waitFor(() => expect(screen.getByRole("option", { name: "Billing" })).toBeInTheDocument());
  fireEvent.change(screen.getByLabelText(en.webForm.category), { target: { value: "1" } });
}

describe("the public request form", () => {
  it("shows exactly the six fields the story allows", async () => {
    const { container } = render(<PublicRequestFormScreen />);

    for (const label of [
      en.webForm.name,
      en.webForm.contact,
      en.webForm.subject,
      en.webForm.category,
      en.webForm.message,
      en.webForm.attachment,
    ]) {
      expect(screen.getByLabelText(label)).toBeInTheDocument();
    }

    /*
     * And no seventh. The set is fixed, so a field added by accident is a
     * change to the product, not a tidy-up.
     */
    const controls = container
      .querySelector("[data-slot='web-form']")!
      .querySelectorAll("input:not([aria-hidden='true']), select, textarea");

    expect(controls).toHaveLength(6);
  });

  it("hides the honeypot from people and sends it empty", async () => {
    const { container } = render(<PublicRequestFormScreen />);

    const honeypot = container.querySelector<HTMLInputElement>("input[name='hp_company']");

    expect(honeypot).not.toBeNull();
    // Untabbable and unannounced: nobody using a keyboard or a screen reader
    // can land on it, so anything in it was put there by a script.
    expect(honeypot?.getAttribute("tabindex")).toBe("-1");
    expect(honeypot?.getAttribute("aria-hidden")).toBe("true");

    await fillIn();
    await userEvent.click(screen.getByRole("button", { name: en.webForm.send }));

    await waitFor(() => expect(submissions).toHaveLength(1));
    expect(submissions[0]!.hp_company).toBe("");
  });

  it("sends when the page was drawn, so the server can refuse an impossible fill time", async () => {
    render(<PublicRequestFormScreen />);

    await fillIn();
    await userEvent.click(screen.getByRole("button", { name: en.webForm.send }));

    await waitFor(() => expect(submissions).toHaveLength(1));

    const renderedAt = submissions[0]!.rendered_at;
    expect(typeof renderedAt).toBe("string");
    expect(Number.isNaN(Date.parse(String(renderedAt)))).toBe(false);
  });

  it("shows the reference and nothing else once it is sent", async () => {
    render(<PublicRequestFormScreen />);

    await fillIn();
    await userEvent.click(screen.getByRole("button", { name: en.webForm.send }));

    await waitFor(() => expect(screen.getByText("TKT-000042")).toBeInTheDocument());

    /*
     * No ticket view. Anybody holding a reference could otherwise read a
     * stranger's conversation.
     */
    expect(screen.queryByLabelText(en.webForm.message)).not.toBeInTheDocument();
  });

  it("explains a refusal rather than going blank", async () => {
    submitStatus = 422;
    submitDetail = "The form was submitted faster than it can be filled in.";

    render(<PublicRequestFormScreen />);

    await fillIn();
    await userEvent.click(screen.getByRole("button", { name: en.webForm.send }));

    // The server's own words: "try again in a moment" and "you have sent
    // several already" are different problems with different answers.
    await waitFor(() => expect(screen.getByText(submitDetail!)).toBeInTheDocument());
    expect(screen.getByLabelText(en.webForm.message)).toBeInTheDocument();
  });
});
