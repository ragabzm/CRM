import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/tickets/new",
}));

import { NewTicketScreen } from "@/components/screens/tickets/NewTicketScreen";

const DEPARTMENTS = [
  { id: 1, name: "Billing" },
  { id: 2, name: "Support" },
];
const CATEGORIES = [{ id: 5, name: "Invoices" }];

const CUSTOMER = {
  id: "01CUSTOMER00000000000000AA",
  reference: "C-3F7KQ2XH",
  full_name: "Hana Yousef",
  department: { id: 1, name: "Billing" },
  state: "active",
  preferred_channel: null,
  identifiers: [],
  updated_at: null,
  deactivated_at: null,
};

interface Call {
  url: string;
  method: string;
  headers: Record<string, string>;
  body: Record<string, unknown> | null;
}

let calls: Call[] = [];
let createStatus = 201;

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  calls = [];
  createStatus = 201;

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = (init?.method ?? "GET").toUpperCase();

      calls.push({
        url,
        method,
        headers: (init?.headers ?? {}) as Record<string, string>,
        body: init?.body ? JSON.parse(String(init.body)) : null,
      });

      if (url.includes("csrf-cookie")) return json({});
      if (url.includes("/customers"))
        return json({ data: [CUSTOMER], meta: { page: 1, per_page: 5, total: 1, last_page: 1 } });

      if (createStatus !== 201) {
        return json(
          {
            type: "x",
            title: "Invalid",
            status: createStatus,
            code: "platform.validation_failed",
            detail: "That customer does not exist.",
          },
          createStatus,
        );
      }

      return json({ id: "01TICKET0000000000000000AA", reference: "TKT-000042", version: 1 }, 201);
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const onCreated = vi.fn();

const renderScreen = (props = {}) =>
  render(
    <NewTicketScreen
      departments={DEPARTMENTS}
      categories={CATEGORIES}
      onCreated={onCreated}
      {...props}
    />,
  );

const creates = () =>
  calls.filter((call) => call.method === "POST" && call.url.endsWith("/tickets"));

async function fillRequired() {
  await userEvent.type(screen.getByLabelText("Subject"), "Invoice is wrong");
  await userEvent.type(screen.getByLabelText("What happened"), "Charged for two seats.");
  await userEvent.type(screen.getByLabelText("Customer"), "hana");

  const option = await screen.findByRole("radio");
  await userEvent.click(option);
}

describe("the new ticket form", () => {
  it("creates a ticket and hands back its id", async () => {
    renderScreen();
    await fillRequired();

    await userEvent.click(screen.getByRole("button", { name: "Create ticket" }));

    await waitFor(() => expect(creates()).toHaveLength(1));
    expect(creates()[0]!.body).toMatchObject({
      subject: "Invoice is wrong",
      customer_id: CUSTOMER.id,
      channel: "agent",
      priority: "normal",
    });
    expect(onCreated).toHaveBeenCalledWith("01TICKET0000000000000000AA");
  });

  it("reuses one idempotency key so a double click cannot open two tickets", async () => {
    renderScreen();
    await fillRequired();

    const button = screen.getByRole("button", { name: "Create ticket" });
    await userEvent.click(button);
    await waitFor(() => expect(creates()).toHaveLength(1));

    await userEvent.click(button);
    await waitFor(() => expect(creates()).toHaveLength(2));

    // The same key both times: the server replays the first response rather
    // than opening a second ticket for one problem.
    expect(creates()[0]!.headers["Idempotency-Key"]).toBe(creates()[1]!.headers["Idempotency-Key"]);
    expect(creates()[0]!.headers["Idempotency-Key"]).toBeTruthy();
  });

  it("will not submit without a subject", async () => {
    renderScreen();

    await userEvent.click(screen.getByRole("button", { name: "Create ticket" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("Give the ticket a subject.");
    expect(creates()).toHaveLength(0);
  });

  it("will not submit without a customer", async () => {
    renderScreen();

    await userEvent.type(screen.getByLabelText("Subject"), "Something");
    await userEvent.type(screen.getByLabelText("What happened"), "Something happened");
    await userEvent.click(screen.getByRole("button", { name: "Create ticket" }));

    // A ticket with no customer cannot be replied to.
    expect(await screen.findByRole("alert")).toHaveTextContent("Choose a customer.");
    expect(creates()).toHaveLength(0);
  });

  it("shows the server's reason when creation is refused", async () => {
    createStatus = 422;

    renderScreen();
    await fillRequired();
    await userEvent.click(screen.getByRole("button", { name: "Create ticket" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("That customer does not exist.");
  });

  it("sends the chosen priority, category and department", async () => {
    renderScreen();
    await fillRequired();

    await userEvent.selectOptions(screen.getByLabelText("Priority"), "urgent");
    await userEvent.selectOptions(screen.getByLabelText("Category"), "5");
    await userEvent.selectOptions(screen.getByLabelText("Department"), "2");
    await userEvent.click(screen.getByRole("button", { name: "Create ticket" }));

    await waitFor(() => expect(creates()).toHaveLength(1));
    expect(creates()[0]!.body).toMatchObject({
      priority: "urgent",
      category_id: 5,
      department_id: 2,
    });
  });

  it("omits an unset category rather than sending an empty one", async () => {
    renderScreen();
    await fillRequired();
    await userEvent.click(screen.getByRole("button", { name: "Create ticket" }));

    await waitFor(() => expect(creates()).toHaveLength(1));
    expect(creates()[0]!.body).not.toHaveProperty("category_id");
    expect(creates()[0]!.body).not.toHaveProperty("department_id");
  });

  it("hides the customer picker when opened from a customer", async () => {
    renderScreen({ customerId: CUSTOMER.id });

    // Opened from their profile, the customer is already decided; asking again
    // invites picking the wrong one.
    expect(screen.queryByLabelText("Customer")).toBeNull();

    await userEvent.type(screen.getByLabelText("Subject"), "From the profile");
    await userEvent.type(screen.getByLabelText("What happened"), "Details");
    await userEvent.click(screen.getByRole("button", { name: "Create ticket" }));

    await waitFor(() => expect(creates()).toHaveLength(1));
    expect(creates()[0]!.body).toMatchObject({ customer_id: CUSTOMER.id });
  });

  it("does not search on every keystroke", async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });

    try {
      renderScreen();

      const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
      await user.type(screen.getByLabelText("Customer"), "hana");

      const before = calls.filter((call) => call.url.includes("/customers")).length;
      // Four characters must not be four requests whose answers land out of
      // order.
      expect(before).toBeLessThanOrEqual(1);
    } finally {
      vi.useRealTimers();
    }
  });
});
