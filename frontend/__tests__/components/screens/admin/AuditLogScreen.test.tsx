import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/admin/audit-log",
}));

import { AuditLogScreen } from "@/components/screens/admin/AuditLogScreen";

const ACTIONS = [
  "auth.sign_in.succeeded",
  "auth.sign_in.failed",
  "user.created",
  "user.updated",
  "user.deactivated",
  "config.changed",
];

const ENTRIES = [
  {
    id: "01AAAAAAAAAAAAAAAAAAAAAAAA",
    occurred_at: "2026-09-02T09:15:00+00:00",
    actor: { id: "7", type: "user", label: "Hana Yousef" },
    action: "user.updated",
    target: { type: "user", id: "41" },
    source_ip: "203.0.113.7",
    request_id: "01REQREQREQREQREQREQREQREQ",
  },
  {
    id: "01BBBBBBBBBBBBBBBBBBBBBBBB",
    occurred_at: "2026-09-02T08:00:00+00:00",
    actor: { id: null, type: "guest", label: "someone@example.test" },
    action: "auth.sign_in.failed",
    target: { type: "user", id: null },
    source_ip: "198.51.100.4",
    request_id: null,
  },
];

/** Query strings the screen actually asked for, in order. */
let requested: string[] = [];
let status = 200;

function page(overrides: Record<string, unknown> = {}) {
  return {
    data: ENTRIES,
    meta: { page: 1, per_page: 25, total: 2, last_page: 1 },
    actions: ACTIONS,
    ...overrides,
  };
}

beforeEach(() => {
  requested = [];
  status = 200;

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);
      requested.push(url);

      if (status !== 200) {
        return new Response(
          JSON.stringify({
            type: "https://errors.ragab-crm/security.forbidden",
            title: "Forbidden",
            status,
            code: "security.forbidden",
          }),
          { status, headers: { "Content-Type": "application/problem+json" } },
        );
      }

      if (url.includes("/audit-entries/")) {
        return new Response(
          JSON.stringify({
            ...ENTRIES[0],
            before: { name: "Before Name" },
            after: { name: "After Name", password: "[REDACTED]" },
          }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        );
      }

      return new Response(JSON.stringify(page()), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

describe("the audit log screen", () => {
  it("explains why nothing here can be edited", async () => {
    render(<AuditLogScreen />);

    // Immutability that is only true in the backend is a property nobody
    // reading the screen can rely on.
    expect(
      await screen.findByText(/written once and never changed or removed/i),
    ).toBeInTheDocument();
  });

  it("renders the recorded facts as columns", async () => {
    render(<AuditLogScreen />);
    await screen.findByText("Hana Yousef");

    // Scoped to the table: the same action labels also fill the filter
    // dropdown, which is the point of building it from the response.
    const table = within(screen.getByRole("table"));

    expect(table.getByText("Hana Yousef")).toBeInTheDocument();
    expect(table.getByText("User updated")).toBeInTheDocument();
    expect(table.getByText("Sign-in failed")).toBeInTheDocument();
    expect(table.getByText("203.0.113.7")).toBeInTheDocument();
    expect(table.getByText("someone@example.test")).toBeInTheDocument();
  });

  it("offers no way to change or remove an entry", async () => {
    render(<AuditLogScreen />);
    await screen.findByText("Hana Yousef");

    await userEvent.click(screen.getAllByTestId("row-actions-trigger")[0]!);

    const menu = await screen.findByRole("menu");
    const items = within(menu)
      .getAllByRole("menuitem")
      .map((item) => item.textContent);

    // View, and only view. Not a disabled Edit — a greyed-out control invites
    // the question of who is allowed to press it.
    expect(items).toEqual(["View"]);
  });

  it("has no edit or delete control anywhere in the DOM", async () => {
    const { container } = render(<AuditLogScreen />);
    await screen.findByText("Hana Yousef");

    for (const forbidden of [/^edit$/i, /^delete$/i, /^remove$/i, /^save$/i]) {
      expect(screen.queryByRole("button", { name: forbidden })).toBeNull();
    }

    expect(container.querySelector("input[type=submit]")).toBeNull();
  });

  it("pins the actor column so the horizontal scroll cannot lose the reader", async () => {
    const { container } = render(<AuditLogScreen />);
    await screen.findByText("Hana Yousef");

    // Every other column is meaningless without knowing whose row it is.
    expect(container.querySelector('[data-pinned="true"]')).not.toBeNull();
  });

  it("scrolls rather than folding a column away", async () => {
    const { container } = render(<AuditLogScreen />);
    await screen.findByText("Hana Yousef");

    // A comparative table: the reader is reading DOWN a column comparing
    // values, and folding one away destroys exactly that.
    expect(container.querySelector('[data-collapse-mode="scroll"]')).not.toBeNull();
  });

  it("shows the before and after side by side, read-only", async () => {
    render(<AuditLogScreen />);
    await screen.findByText("Hana Yousef");

    await userEvent.click(screen.getAllByTestId("row-actions-trigger")[0]!);
    await userEvent.click(await screen.findByRole("menuitem", { name: "View" }));

    const dialog = await screen.findByRole("dialog");

    expect(within(dialog).getByText("Before")).toBeInTheDocument();
    expect(within(dialog).getByText("After")).toBeInTheDocument();
    expect(within(dialog).getByText(/Before Name/)).toBeInTheDocument();

    // Read-only: no inputs, no save.
    expect(within(dialog).queryByRole("textbox")).toBeNull();
    expect(within(dialog).queryByRole("button", { name: /save/i })).toBeNull();
  });

  it("renders the forbidden surface when the API refuses", async () => {
    status = 403;

    render(<AuditLogScreen />);

    expect(await screen.findByText("You do not have access to the audit log")).toBeInTheDocument();

    // Not an error banner: a 403 is an answer, not something to retry.
    expect(screen.queryByText(/could not be loaded/i)).toBeNull();
    expect(screen.queryByRole("table")).toBeNull();
  });

  it("builds the action filter from what the server records", async () => {
    render(<AuditLogScreen />);
    await screen.findByText("Hana Yousef");

    const select = screen.getByRole("combobox", { name: "What" });
    const options = within(select)
      .getAllByRole("option")
      .map((option) => (option as HTMLOptionElement).value);

    // From the response, not from a second list in the frontend that drifts.
    expect(options).toEqual(["", ...ACTIONS]);
  });

  it("offers exactly three filters and no more", async () => {
    const { container } = render(<AuditLogScreen />);
    await screen.findByText("Hana Yousef");

    const bar = container.querySelector('[data-slot="audit-filters"]')!;
    const labels = within(bar as HTMLElement)
      .getAllByRole("textbox")
      .concat(within(bar as HTMLElement).getAllByRole("combobox"))
      .map((control) => control.getAttribute("type") ?? "select");

    // Who, what, when — a fourth facet is a query nobody can index and a bar
    // nobody can read.
    expect(within(bar as HTMLElement).queryByLabelText(/target/i)).toBeNull();
    expect(within(bar as HTMLElement).queryByLabelText(/ip/i)).toBeNull();
    expect(labels.length).toBeGreaterThan(0);
  });

  it("sends each filter to the API and returns to the first page", async () => {
    render(<AuditLogScreen />);
    await screen.findByText("Hana Yousef");

    await userEvent.type(screen.getByLabelText("Who"), "hana");
    await waitFor(() => expect(requested.at(-1)).toContain("actor_search=hana"));

    await userEvent.selectOptions(screen.getByRole("combobox", { name: "What" }), "user.created");
    await waitFor(() => expect(requested.at(-1)).toContain("action=user.created"));

    // Staying on page 4 of a narrower result set shows an empty screen that
    // reads as "no matches".
    expect(requested.at(-1)).toContain("page=1");
  });

  it("omits an empty filter rather than sending a blank value", async () => {
    render(<AuditLogScreen />);
    await screen.findByText("Hana Yousef");

    await userEvent.click(screen.getByRole("button", { name: "Clear filters" }));

    // `?action=` is a value the server has to reject, not an absent filter.
    await waitFor(() => expect(requested.at(-1)).not.toContain("action="));
  });

  it("names the timezone the dates are read in", async () => {
    render(<AuditLogScreen />);

    // "Entries on the 1st" means different rows depending on the answer, and
    // the reader cannot see ours.
    expect(await screen.findByText(/UTC/)).toBeInTheDocument();
  });

  it("renders an unrecognised action as its raw name rather than blank", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(
        async () =>
          new Response(
            JSON.stringify({
              data: [{ ...ENTRIES[0], action: "something.nobody_added" }],
              meta: { page: 1, per_page: 25, total: 1, last_page: 1 },
              actions: ["something.nobody_added"],
            }),
            { status: 200, headers: { "Content-Type": "application/json" } },
          ),
      ),
    );

    render(<AuditLogScreen />);
    await screen.findByText("Hana Yousef");

    // Worse-looking and still true. The screen must not go blank because the
    // server recorded something the console has no copy for yet.
    expect(
      within(screen.getByRole("table")).getByText("something.nobody_added"),
    ).toBeInTheDocument();
  });

  it("isolates the Latin values so Arabic prose cannot reorder them", async () => {
    render(<AuditLogScreen />, { locale: "ar" });

    const ip = await screen.findByText("203.0.113.7");

    // An IP or a ULID inside Arabic prose reverses without this.
    expect(ip.closest("bdi")).not.toBeNull();
  });

  it("shows the empty state when a filter matches nothing", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(
        async () =>
          new Response(
            JSON.stringify({
              data: [],
              meta: { page: 1, per_page: 25, total: 0, last_page: 1 },
              actions: ACTIONS,
            }),
            { status: 200, headers: { "Content-Type": "application/json" } },
          ),
      ),
    );

    render(<AuditLogScreen />);

    expect(await screen.findByText("No entries for this selection")).toBeInTheDocument();
  });
});
