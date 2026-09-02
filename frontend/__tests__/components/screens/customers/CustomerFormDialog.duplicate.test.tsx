import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/customers",
}));

import { CustomerFormDialog } from "@/components/domain/CustomerFormDialog/CustomerFormDialog";

import { customer, DEPARTMENTS, match } from "./fixtures";

interface Call {
  url: string;
  method: string;
  body: Record<string, unknown> | null;
}

let calls: Call[] = [];
let matches = [match()];
/**
 * What the CREATE endpoint offers, separately from the preview.
 *
 * Two variables because the interesting case is exactly when they disagree:
 * the client's preview came back clean and the server still refused, which is
 * what happens when someone else created the record a moment earlier.
 */
let serverMatches: ReturnType<typeof match>[] | null = null;

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  calls = [];
  matches = [match()];
  serverMatches = null;

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = (init?.method ?? "GET").toUpperCase();
      const body = init?.body ? JSON.parse(String(init.body)) : null;

      calls.push({ url, method, body });

      if (url.includes("csrf-cookie")) return json({});
      if (url.includes("/duplicates/preview")) return json({ matches });

      if (url.endsWith("/customers") && method === "POST") {
        if (serverMatches !== null && body?.confirm_create_duplicate !== true) {
          return json(
            {
              type: "x",
              title: "This person may already exist",
              status: 409,
              code: "customers.duplicate_offer",
              matches: serverMatches,
            },
            409,
          );
        }

        return json(customer({ full_name: String(body?.full_name ?? "") }), 201);
      }

      return json(customer());
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const onSaved = vi.fn();
const onOpenExisting = vi.fn();

function renderDialog(overrides = {}) {
  return render(
    <CustomerFormDialog
      open
      onOpenChange={vi.fn()}
      departments={DEPARTMENTS}
      onSaved={onSaved}
      onOpenExisting={onOpenExisting}
      {...overrides}
    />,
  );
}

async function fillAndSubmit(name = "Hana Y", value = "hana@example.test") {
  await userEvent.type(screen.getByLabelText("Full name"), name);
  await userEvent.type(screen.getAllByLabelText("Email")[1]!, value);
  await userEvent.click(screen.getByRole("button", { name: "Save" }));
}

const creates = () =>
  calls.filter((call) => call.url.endsWith("/customers") && call.method === "POST");

describe("the duplicate offer", () => {
  it("offers the existing customer instead of creating a second one", async () => {
    renderDialog();
    await fillAndSubmit();

    const offer = await screen.findByRole("alert");
    expect(within(offer).getByText("This person may already exist")).toBeInTheDocument();
    expect(within(offer).getByText("Hana Yousef")).toBeInTheDocument();

    // Nothing was created — the agent gets to choose.
    expect(creates()).toHaveLength(0);
  });

  it("names the detail that matched", async () => {
    renderDialog();
    await fillAndSubmit();

    const offer = await screen.findByRole("alert");

    // So the agent can see WHY it matched rather than being told to look.
    expect(within(offer).getByText("hana@example.test")).toBeInTheDocument();
  });

  it("marks a deactivated match as deactivated", async () => {
    matches = [match({ state: "inactive" })];

    renderDialog();
    await fillAndSubmit();

    // Someone returning after two years is the duplicate most worth catching,
    // and their old record already holds the history.
    expect(within(await screen.findByRole("alert")).getByText("Deactivated")).toBeInTheDocument();
  });

  it("opens the existing record when asked", async () => {
    renderDialog();
    await fillAndSubmit();

    await userEvent.click(
      within(await screen.findByRole("alert")).getByRole("button", { name: "Open existing" }),
    );

    expect(onOpenExisting).toHaveBeenCalledWith("01BBBBBBBBBBBBBBBBBBBBBBBB");
    expect(creates()).toHaveLength(0);
  });

  it("creates anyway when the agent confirms", async () => {
    renderDialog();
    await fillAndSubmit();

    await userEvent.click(
      within(await screen.findByRole("alert")).getByRole("button", { name: "Create anyway" }),
    );

    // Two people in one household really do share a phone number.
    await waitFor(() => expect(creates()).toHaveLength(1));
    expect(creates()[0]!.body?.confirm_create_duplicate).toBe(true);
  });

  it("explains when creating a second record is legitimate", async () => {
    renderDialog();
    await fillAndSubmit();

    expect(
      within(await screen.findByRole("alert")).getByText(/share a phone number/i),
    ).toBeInTheDocument();
  });

  it("never disables the create-anyway route", async () => {
    renderDialog();
    await fillAndSubmit();

    // It offers; it does not block. A disabled button is a block.
    expect(
      within(await screen.findByRole("alert")).getByRole("button", { name: "Create anyway" }),
    ).toBeEnabled();
  });

  it("creates straight away when nothing matches", async () => {
    matches = [];

    renderDialog();
    await fillAndSubmit("Omar Saleh", "omar@example.test");

    await waitFor(() => expect(creates()).toHaveLength(1));
    expect(creates()[0]!.body?.confirm_create_duplicate).toBeUndefined();
    expect(onSaved).toHaveBeenCalled();
  });

  it("still shows the offer when only the server catches it", async () => {
    // The client preview can race a customer created a moment earlier, so the
    // server's answer is the authoritative one.
    matches = [];
    serverMatches = [match()];

    renderDialog();
    await fillAndSubmit();

    await waitFor(() => expect(creates()).toHaveLength(1));

    const offer = await screen.findByRole("alert");
    expect(offer).toHaveTextContent("This person may already exist");
    // Rendered from the SERVER's payload, since the preview returned nothing.
    expect(within(offer).getByText("Hana Yousef")).toBeInTheDocument();
  });

  it("saves anyway when the preview itself fails", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = String(input);
        const method = (init?.method ?? "GET").toUpperCase();
        calls.push({ url, method, body: init?.body ? JSON.parse(String(init.body)) : null });

        if (url.includes("csrf-cookie")) return json({});
        if (url.includes("/duplicates/preview"))
          return json({ title: "boom", status: 500, code: "x" }, 500);

        return json(customer(), 201);
      }),
    );

    renderDialog();
    await fillAndSubmit();

    // A courtesy check that fails must not stop the agent working. The server
    // still checks on create.
    await waitFor(() => expect(creates()).toHaveLength(1));
  });

  it("refuses a customer with no way to reach them", async () => {
    renderDialog();

    await userEvent.type(screen.getByLabelText("Full name"), "Nobody");
    await userEvent.click(screen.getByRole("button", { name: "Save" }));

    expect(
      await screen.findByText(/at least one email address or phone number/i),
    ).toBeInTheDocument();
    expect(creates()).toHaveLength(0);
  });

  it("catches the same detail typed twice before sending it", async () => {
    renderDialog();

    await userEvent.type(screen.getByLabelText("Full name"), "Hana");
    await userEvent.type(screen.getAllByLabelText("Email")[1]!, "+44 20 7946 0958");

    await userEvent.click(screen.getByRole("button", { name: "Add phone" }));
    const phones = screen.getAllByLabelText("Phone");
    await userEvent.type(phones[phones.length - 1]!, "020 7946 0958");

    // Both rows are phones for the check to catch them; switch the first.
    await userEvent.selectOptions(screen.getAllByLabelText(/Contact details 1/)[0]!, "phone");
    await userEvent.click(screen.getByRole("button", { name: "Save" }));

    // The normalisation mirror earns its place here: these two strings are the
    // same number, and only normalising reveals it.
    expect(await screen.findByText(/already listed above/i)).toBeInTheDocument();
    expect(creates()).toHaveLength(0);
  });

  it("does not offer a customer as their own duplicate while editing", async () => {
    renderDialog({ customer: customer() });

    await userEvent.clear(screen.getByLabelText("Full name"));
    await userEvent.type(screen.getByLabelText("Full name"), "Hana Yousef Renamed");
    await userEvent.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => {
      const preview = calls.find((call) => call.url.includes("/duplicates/preview"));
      expect(preview?.body?.exclude_customer_id).toBe("01AAAAAAAAAAAAAAAAAAAAAAAA");
    });
  });
});
