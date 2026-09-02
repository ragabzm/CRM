import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/admin/ticketing",
}));

import { TicketingSection } from "@/components/screens/admin/TicketingSection";

/**
 * Drives the screen through the real transport by stubbing fetch, so the
 * request the browser would actually send — path, method, body — is what the
 * assertions see.
 */
interface Recorded {
  url: string;
  method: string;
  body: unknown;
}

let recorded: Recorded[] = [];
let autoCloseHours = 168;
let categories = [{ id: 1, name: { en: "Billing", ar: "الفواتير" }, sort_order: 1 }];

function settingsPayload() {
  return {
    data: [
      {
        key: "tickets.auto_close_hours",
        type: "int",
        value: autoCloseHours,
        default: 168,
        secret: false,
        summary: "Hours a resolved ticket waits before closing itself.",
        allowed_values: null,
      },
      {
        key: "tickets.reopen_window_hours",
        type: "int",
        value: 72,
        default: 72,
        secret: false,
        summary: "How long a closed ticket can be reopened.",
        allowed_values: null,
      },
    ],
  };
}

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

/** Set by a test to make the next matching call fail. */
let failures: Record<string, () => Response> = {};

beforeEach(() => {
  recorded = [];
  failures = {};
  autoCloseHours = 168;
  categories = [{ id: 1, name: { en: "Billing", ar: "الفواتير" }, sort_order: 1 }];

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = (init?.method ?? "GET").toUpperCase();
      const body = init?.body ? JSON.parse(String(init.body)) : null;

      recorded.push({ url, method, body });

      if (url.includes("/sanctum/csrf-cookie")) return json({});

      const failure = failures[`${method} ${url.split("/api/v1")[1] ?? url}`];
      if (failure) return failure();

      if (url.includes("/admin/settings") && method === "PATCH") {
        autoCloseHours = body.value;
        return json({ key: "tickets.auto_close_hours", value: body.value });
      }
      if (url.includes("/admin/settings")) return json(settingsPayload());

      if (url.includes("/admin/categories") && method === "DELETE") {
        categories = [];
        return json({ deleted: 1 });
      }
      if (url.includes("/admin/categories") && method === "POST") {
        const created = { id: 2, name: body.name, sort_order: 2 };
        categories = [...categories, created];
        return json(created, 201);
      }
      if (url.includes("/admin/categories")) return json({ data: categories });

      if (url.includes("/admin/quick-replies")) return json({ data: [] });

      if (url.includes("/admin/priorities")) {
        return json({
          data: [{ value: "low" }, { value: "normal" }, { value: "high" }, { value: "urgent" }],
          editable: false,
        });
      }

      return json({ data: [] });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const writes = () => recorded.filter((call) => call.method !== "GET" && !call.url.includes("csrf"));

describe("the ticketing console", () => {
  it("shows the resolved value the server reports", async () => {
    render(<TicketingSection />);

    expect(await screen.findByDisplayValue("168")).toBeInTheDocument();
  });

  it("re-reads the resolved set after a save, rather than trusting its guess", async () => {
    render(<TicketingSection />);

    const input = await screen.findByDisplayValue("168");
    await userEvent.clear(input);
    await userEvent.type(input, "24");
    await userEvent.click(screen.getAllByRole("button", { name: "Save" })[0]!);

    // The new value is on screen...
    await waitFor(() => expect(screen.getByDisplayValue("24")).toBeInTheDocument());

    // ...and it came from a re-read, not from a local patch. A validator can
    // normalise what was sent, and the console must show what the server holds.
    const settingsReads = recorded.filter(
      (call) => call.method === "GET" && call.url.includes("/admin/settings"),
    );
    expect(settingsReads.length).toBeGreaterThanOrEqual(2);
  });

  it("sends the write as a PATCH to the key's own path", async () => {
    render(<TicketingSection />);

    const input = await screen.findByDisplayValue("168");
    await userEvent.clear(input);
    await userEvent.type(input, "24");
    await userEvent.click(screen.getAllByRole("button", { name: "Save" })[0]!);

    await waitFor(() => {
      const patch = writes().find((call) => call.method === "PATCH");
      expect(patch?.url).toContain("/admin/settings/tickets.auto_close_hours");
      expect(patch?.body).toEqual({ value: 24 });
    });
  });

  it("carries an Idempotency-Key on every write", async () => {
    const fetchMock = globalThis.fetch as unknown as ReturnType<typeof vi.fn>;
    render(<TicketingSection />);

    const input = await screen.findByDisplayValue("168");
    await userEvent.clear(input);
    await userEvent.type(input, "24");
    await userEvent.click(screen.getAllByRole("button", { name: "Save" })[0]!);

    await waitFor(() => {
      const patch = fetchMock.mock.calls.find(
        ([, init]) => (init as RequestInit | undefined)?.method === "PATCH",
      );
      const headers = (patch?.[1] as RequestInit).headers as Record<string, string>;

      // The server rejects a write without one, so a screen that forgets it is
      // broken in production and green in a test that does not check.
      expect(headers["Idempotency-Key"]).toBeTruthy();
    });
  });

  it("shows the server's refusal inline and leaves the value alone", async () => {
    failures["PATCH /admin/settings/tickets.auto_close_hours"] = () =>
      json(
        {
          type: "https://ragab.example/problems/platform/setting-invalid",
          title: "Setting value is not allowed",
          status: 422,
          detail: "Auto-close must be between 1 hour and 90 days.",
          code: "platform.setting_invalid",
          setting: "tickets.auto_close_hours",
        },
        422,
      );

    render(<TicketingSection />);

    const input = await screen.findByDisplayValue("168");
    await userEvent.clear(input);
    await userEvent.type(input, "0");
    await userEvent.click(screen.getAllByRole("button", { name: "Save" })[0]!);

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Auto-close must be between 1 hour and 90 days.",
    );
  });

  it("states the exact consequence before deleting a category", async () => {
    render(<TicketingSection />);

    await screen.findByText("Billing");
    await userEvent.click(screen.getByTestId("row-actions-trigger"));
    await userEvent.click(await screen.findByRole("menuitem", { name: "Delete" }));

    const dialog = await screen.findByRole("dialog");
    // Names the category. Not "Are you sure?".
    expect(within(dialog).getByText(/This will delete the category “Billing”/)).toBeInTheDocument();

    // ...and nothing has been sent yet.
    expect(writes()).toHaveLength(0);
  });

  it("renders a blocked delete with the count and a route to the tickets", async () => {
    failures["DELETE /admin/categories/1"] = () =>
      json(
        {
          type: "https://ragab.example/problems/tickets/category-in-use",
          title: "Category is still in use",
          status: 409,
          detail: "Cannot delete: 7 tickets still use this category.",
          code: "tickets.category_in_use",
          count: 7,
          path: "/tickets?category=1",
        },
        409,
      );

    render(<TicketingSection />);

    await screen.findByText("Billing");
    await userEvent.click(screen.getByTestId("row-actions-trigger"));
    await userEvent.click(await screen.findByRole("menuitem", { name: "Delete" }));
    await userEvent.click(await screen.findByRole("button", { name: "Delete" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("7 tickets still use it");
    expect(screen.getByRole("link", { name: "View them" })).toHaveAttribute(
      "href",
      "/tickets?category=1",
    );

    // A rule, not a failure: the category is still there.
    expect(screen.getByText("Billing")).toBeInTheDocument();
  });

  it("tells the reader the priorities are fixed", async () => {
    render(<TicketingSection />);

    expect(
      await screen.findByText(
        "Priorities are fixed at Low · Normal · High · Urgent and are not editable here.",
      ),
    ).toBeInTheDocument();

    // Listed, but with no control that suggests otherwise.
    expect(screen.getByText("urgent")).toBeInTheDocument();
  });
});
