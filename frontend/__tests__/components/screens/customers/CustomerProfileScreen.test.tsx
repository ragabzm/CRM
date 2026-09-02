import { render, screen, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/customers/01AAAAAAAAAAAAAAAAAAAAAAAA",
}));

import { CustomerProfileScreen } from "@/components/screens/customers/CustomerProfileScreen";

import { customer, DEPARTMENTS } from "./fixtures";

let record = customer();
let status = 200;
let calls: string[] = [];

function json(body: unknown, code = 200) {
  return new Response(JSON.stringify(body), {
    status: code,
    headers: { "Content-Type": code >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  record = customer();
  status = 200;
  calls = [];

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      calls.push(`${(init?.method ?? "GET").toUpperCase()} ${url}`);

      if (url.includes("csrf-cookie")) return json({});
      if (status !== 200) return json({ title: "no", status, code: "security.forbidden" }, status);

      // The profile mounts the notes and attachments lanes, which fetch on
      // mount. Empty is the uninteresting answer; both have their own suites.
      if (url.includes("/notes")) return json({ data: [] });
      if (url.includes("/attachments")) return json({ data: [] });

      if (url.includes("/deactivate")) {
        record = customer({ state: "inactive", deactivated_at: "2026-09-02T10:00:00+00:00" });

        return json(record);
      }

      if (url.includes("/reactivate")) {
        record = customer({ state: "active", deactivated_at: null });

        return json(record);
      }

      return json(record);
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

function renderProfile(options: { locale?: "en" | "ar" } = {}) {
  return render(
    <CustomerProfileScreen
      customerId="01AAAAAAAAAAAAAAAAAAAAAAAA"
      departments={DEPARTMENTS}
      onOpenCustomer={vi.fn()}
    />,
    options,
  );
}

describe("the customer profile", () => {
  it("shows every way to reach the customer", async () => {
    renderProfile();

    await screen.findByText("Hana Yousef");

    // Several emails AND several phones — the reason they live in their own
    // table rather than in columns.
    expect(screen.getByText("hana@example.test")).toBeInTheDocument();
    expect(screen.getByText("+44 20 7946 0958")).toBeInTheDocument();
    expect(screen.getByText("Primary")).toBeInTheDocument();
  });

  it("shows the reference and the department", async () => {
    renderProfile();

    expect(await screen.findByText("C-3F7KQ2XH")).toBeInTheDocument();
    expect(screen.getByText("Billing")).toBeInTheDocument();
  });

  it("does not show an organisation anywhere", async () => {
    const { container } = renderProfile();
    await screen.findByText("Hana Yousef");

    // Out of scope by decision. It gets reversed by somebody adding a chip,
    // not by a design discussion.
    expect(container.textContent).not.toMatch(/organisation|organization|company/i);
  });

  it("says what deactivation actually does before doing it", async () => {
    renderProfile();
    await screen.findByText("Hana Yousef");

    await userEvent.click(screen.getByRole("button", { name: "Deactivate" }));

    const dialog = await screen.findByRole("dialog");

    // Names the person and says the history survives. "Are you sure?" would
    // leave the agent guessing whether this destroys their tickets.
    expect(within(dialog).getByText(/Hana Yousef/)).toBeInTheDocument();
    expect(within(dialog).getByText(/tickets, notes and history stay/i)).toBeInTheDocument();
  });

  it("does not deactivate until confirmed", async () => {
    renderProfile();
    await screen.findByText("Hana Yousef");

    await userEvent.click(screen.getByRole("button", { name: "Deactivate" }));
    await userEvent.click(
      within(await screen.findByRole("dialog")).getByRole("button", { name: "Cancel" }),
    );

    expect(calls.some((call) => call.includes("/deactivate"))).toBe(false);
  });

  it("deactivates and offers reactivation", async () => {
    renderProfile();
    await screen.findByText("Hana Yousef");

    await userEvent.click(screen.getByRole("button", { name: "Deactivate" }));
    await userEvent.click(
      within(await screen.findByRole("dialog")).getByRole("button", { name: "Deactivate" }),
    );

    // Never a delete: the record is the anchor for everything attached to it,
    // and it comes back.
    expect(await screen.findByRole("button", { name: "Reactivate" })).toBeInTheDocument();
  });

  it("still renders a deactivated customer in full", async () => {
    record = customer({ state: "inactive", deactivated_at: "2026-09-02T10:00:00+00:00" });

    renderProfile();

    // A direct link from a two-year-old ticket must open the person, not a
    // 404 that reads as data loss.
    expect(await screen.findByText("Hana Yousef")).toBeInTheDocument();
    expect(screen.getByText("hana@example.test")).toBeInTheDocument();
    expect(screen.getByText("Inactive")).toBeInTheDocument();
  });

  it("says the department does not restrict access", async () => {
    renderProfile();

    expect(await screen.findByText(/never limit who can see them/i)).toBeInTheDocument();
  });

  it("renders the forbidden surface when refused", async () => {
    status = 403;

    renderProfile();

    expect(
      await screen.findByText("You do not have access to customer records"),
    ).toBeInTheDocument();
  });

  it("says so plainly when the customer does not exist", async () => {
    status = 404;

    renderProfile();

    expect(await screen.findByText("That customer does not exist.")).toBeInTheDocument();
  });

  it("isolates Latin values in Arabic", async () => {
    (renderProfile(), { locale: "ar" });

    const value = await screen.findByText("+44 20 7946 0958");

    // A phone number reverses inside Arabic prose without this.
    expect(value.closest("bdi")).not.toBeNull();
  });
});
