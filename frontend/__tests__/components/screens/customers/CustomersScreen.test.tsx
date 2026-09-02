import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/customers",
}));

import { CustomersScreen } from "@/components/screens/customers/CustomersScreen";

import { customer, DEPARTMENTS, page } from "./fixtures";

let requested: string[] = [];
let status = 200;
let rows = [customer()];

function json(body: unknown, code = 200) {
  return new Response(JSON.stringify(body), {
    status: code,
    headers: { "Content-Type": code >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  requested = [];
  status = 200;
  rows = [customer()];

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);
      requested.push(url);

      if (url.includes("csrf-cookie")) return json({});

      if (status !== 200) {
        return json({ type: "x", title: "Forbidden", status, code: "security.forbidden" }, status);
      }

      return json(page(rows));
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const open = vi.fn();

describe("the customers list", () => {
  it("shows a customer's identifying facts", async () => {
    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />);

    await screen.findByText("Hana Yousef");

    const table = within(screen.getByRole("table"));

    // getAllByText: in fold mode a secondary column renders twice — once in its
    // own cell for the desktop band and once in the row's meta line for the
    // narrower ones. Neither is a duplicate on screen at any single width.
    expect(table.getAllByText("C-3F7KQ2XH").length).toBeGreaterThan(0);
    expect(table.getAllByText("hana@example.test").length).toBeGreaterThan(0);
    expect(table.getAllByText("+44 20 7946 0958").length).toBeGreaterThan(0);
    expect(table.getAllByText("Billing").length).toBeGreaterThan(0);
  });

  it("shows the value as the customer gave it, not the normalised form", async () => {
    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />);
    await screen.findByText("Hana Yousef");

    // Nobody wants their phone number handed back with the punctuation gone.
    expect(within(screen.getByRole("table")).queryByText("2079460958")).toBeNull();
  });

  it("searches on one box across every kind of fact", async () => {
    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />);
    await screen.findByText("Hana Yousef");

    await userEvent.type(screen.getByRole("searchbox"), "yous");

    // One box, not a "search by" dropdown: an agent with a caller on the line
    // has one fact and should not have to say which kind it is.
    await waitFor(() => expect(requested.at(-1)).toContain("q=yous"));
  });

  it("defaults to active customers only", async () => {
    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />);

    // Someone who left three years ago should not clutter today's lookup.
    await waitFor(() => expect(requested[0]).toContain("state=active"));
  });

  it("can be asked for deactivated customers", async () => {
    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />);
    await screen.findByText("Hana Yousef");

    await userEvent.click(screen.getByRole("button", { name: "Inactive" }));

    await waitFor(() => expect(requested.at(-1)).toContain("state=inactive"));
  });

  it("announces which state filter is active, not only colours it", async () => {
    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />);
    await screen.findByText("Hana Yousef");

    // The state has to survive greyscale and a screen reader.
    expect(screen.getByRole("button", { name: "Active" })).toHaveAttribute("aria-pressed", "true");
    expect(screen.getByRole("button", { name: "All" })).toHaveAttribute("aria-pressed", "false");
  });

  it("filters by department and clears the filter again", async () => {
    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />);
    await screen.findByText("Hana Yousef");

    await userEvent.selectOptions(screen.getByLabelText("Department"), "2");
    await waitFor(() => expect(requested.at(-1)).toContain("department_id=2"));

    await userEvent.selectOptions(screen.getByLabelText("Department"), "");

    // Cleared means absent, not `department_id=`.
    await waitFor(() => expect(requested.at(-1)).not.toContain("department_id"));
  });

  it("says out loud that department does not restrict access", async () => {
    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />);

    // So nobody assumes the filter is doing security work.
    expect(await screen.findByText(/never limit who can see them/i)).toBeInTheDocument();
  });

  it("returns to the first page whenever a filter changes", async () => {
    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />);
    await screen.findByText("Hana Yousef");

    await userEvent.click(screen.getByRole("button", { name: "All" }));

    // Staying on page 4 of a narrower result shows an empty screen that reads
    // as "no matches".
    await waitFor(() => expect(requested.at(-1)).toContain("page=1"));
  });

  it("shows the empty state when nothing matches", async () => {
    rows = [];

    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />);

    expect(await screen.findByText("No customers match this search")).toBeInTheDocument();
  });

  it("renders the forbidden surface when access is refused", async () => {
    status = 403;

    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />);

    expect(
      await screen.findByText("You do not have access to customer records"),
    ).toBeInTheDocument();
    expect(screen.queryByRole("table")).toBeNull();
  });

  it("opens a customer from the row menu", async () => {
    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />);
    await screen.findByText("Hana Yousef");

    await userEvent.click(screen.getByTestId("row-actions-trigger"));
    await userEvent.click(await screen.findByRole("menuitem", { name: "Open" }));

    expect(open).toHaveBeenCalledWith("01AAAAAAAAAAAAAAAAAAAAAAAA");
  });

  it("isolates Latin values so Arabic prose cannot reorder them", async () => {
    render(<CustomersScreen departments={DEPARTMENTS} onOpenCustomer={open} />, { locale: "ar" });

    const reference = await screen.findByText("C-3F7KQ2XH");

    expect(reference.closest("bdi")).not.toBeNull();
  });
});
