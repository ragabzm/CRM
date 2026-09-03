import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/customers",
}));

import { CustomersScreen } from "@/components/screens/customers/CustomersScreen";

/**
 * A failed load and an empty result are different facts and must never be
 * shown together.
 *
 * They used to be: a red "Customers could not be loaded." rendered directly
 * above "No customers match this search". One says the request failed, the
 * other says it succeeded and there is nothing to show — and the reader had no
 * way to tell which had actually happened.
 */

let status = 200;
let rows: unknown[] = [];

function json(body: unknown, code = 200) {
  return new Response(JSON.stringify(body), {
    status: code,
    headers: { "Content-Type": code >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  status = 200;
  rows = [];

  vi.stubGlobal(
    "fetch",
    vi.fn(async () => {
      if (status !== 200) return json({ status, code: "platform.internal_error" }, status);

      return json({
        data: rows,
        meta: { page: 1, per_page: 25, total: rows.length, last_page: 1 },
      });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const renderScreen = () => render(<CustomersScreen departments={[]} onOpenCustomer={vi.fn()} />);

describe("the customers list when the load fails", () => {
  it("reports the failure", async () => {
    status = 500;
    renderScreen();

    expect(await screen.findByRole("alert")).toHaveTextContent("Customers could not be loaded.");
  });

  it("does not also claim there are no matches", async () => {
    status = 500;
    renderScreen();

    await screen.findByRole("alert");

    // A failed load has no idea whether results exist. Saying "none match" is a
    // claim it cannot support.
    expect(screen.queryByText("No customers match this search")).toBeNull();
  });

  it("offers a way to try again", async () => {
    status = 500;
    renderScreen();

    const alert = await screen.findByRole("alert");

    status = 200;
    rows = [
      {
        id: "01C1",
        reference: "C-9XQ4TR2M",
        full_name: "Hana Yousef",
        department: { id: 1, name: "Support" },
        state: "active",
        preferred_channel: null,
        identifiers: [],
        updated_at: "2026-09-02T09:00:00Z",
        deactivated_at: null,
      },
    ];

    await userEvent.click(within(alert).getByRole("button", { name: "Retry" }));

    expect(await screen.findByText("Hana Yousef")).toBeInTheDocument();
  });

  it("clears the banner once a retry succeeds", async () => {
    status = 500;
    renderScreen();

    const alert = await screen.findByRole("alert");
    status = 200;

    await userEvent.click(within(alert).getByRole("button", { name: "Retry" }));

    await waitFor(() => expect(screen.queryByRole("alert")).toBeNull());
  });
});

describe("the customers list when the load succeeds", () => {
  it("says there are no matches, and reports no failure", async () => {
    renderScreen();

    expect(await screen.findByText("No customers match this search")).toBeInTheDocument();
    expect(screen.queryByRole("alert")).toBeNull();
  });

  it("does not flash the empty state before the first response arrives", async () => {
    renderScreen();

    // Before the fetch resolves nothing is known yet, and "no customers match"
    // is a claim about an answer that has not come back.
    expect(screen.queryByText("No customers match this search")).toBeNull();

    await screen.findByText("No customers match this search");
  });
});
