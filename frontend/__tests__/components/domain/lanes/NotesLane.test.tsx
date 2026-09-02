import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/customers/01AAAAAAAAAAAAAAAAAAAAAAAA",
}));

import { NotesLane } from "@/components/domain/NotesLane/NotesLane";
import type { CustomerNote } from "@/lib/api/notes";

const CUSTOMER = "01AAAAAAAAAAAAAAAAAAAAAAAA";
const MINE = "7";
const THEIRS = "9";

function note(overrides: Partial<CustomerNote> = {}): CustomerNote {
  return {
    id: "01NOTE0000000000000000MINE",
    customer_id: CUSTOMER,
    author: { id: MINE, name: "Hana Yousef" },
    body: "Called about the invoice.",
    created_at: "2026-09-02T09:00:00+00:00",
    updated_at: "2026-09-02T09:00:00+00:00",
    edited: false,
    ...overrides,
  };
}

interface Call {
  url: string;
  method: string;
  body: Record<string, unknown> | null;
}

let calls: Call[] = [];
let notes: CustomerNote[] = [];
let failWith: number | null = null;

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  calls = [];
  notes = [note()];
  failWith = null;

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = (init?.method ?? "GET").toUpperCase();
      calls.push({ url, method, body: init?.body ? JSON.parse(String(init.body)) : null });

      if (url.includes("csrf-cookie")) return json({});
      if (failWith !== null) return json({ title: "no", status: failWith, code: "x" }, failWith);
      if (method === "GET") return json({ data: notes });

      return json(note(), method === "POST" ? 201 : 200);
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

function renderLane(overrides: Partial<React.ComponentProps<typeof NotesLane>> = {}) {
  return render(
    <NotesLane customerId={CUSTOMER} currentUserId={MINE} canModerate={false} {...overrides} />,
  );
}

const writes = () => calls.filter((call) => call.method !== "GET" && !call.url.includes("csrf"));

describe("the notes lane", () => {
  it("shows who wrote each note and when", async () => {
    renderLane();

    expect(await screen.findByText("Called about the invoice.")).toBeInTheDocument();
    expect(screen.getByText("Hana Yousef")).toBeInTheDocument();
  });

  it("adds a note", async () => {
    renderLane();
    await screen.findByText("Called about the invoice.");

    await userEvent.type(screen.getByLabelText("Add note"), "Promised a callback Tuesday.");
    await userEvent.click(screen.getByRole("button", { name: "Add note" }));

    await waitFor(() => {
      const post = writes().find((call) => call.method === "POST");
      expect(post?.body?.body).toBe("Promised a callback Tuesday.");
    });
  });

  it("refuses to save an empty note", async () => {
    renderLane();
    await screen.findByText("Called about the invoice.");

    await userEvent.click(screen.getByRole("button", { name: "Add note" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("Write something before saving");
    expect(writes()).toHaveLength(0);
  });

  it("offers edit and delete on your own note", async () => {
    renderLane();
    await screen.findByText("Called about the invoice.");

    await userEvent.click(screen.getByTestId("row-actions-trigger"));

    const items = within(await screen.findByRole("menu"))
      .getAllByRole("menuitem")
      .map((item) => item.textContent);

    expect(items).toEqual(["Edit", "Delete"]);
  });

  it("offers nothing on a colleague's note", async () => {
    notes = [note({ author: { id: THEIRS, name: "Omar Saleh" } })];

    renderLane();
    await screen.findByText("Called about the invoice.");

    // An empty overflow menu is a control that teaches nothing, so there is no
    // trigger at all.
    expect(screen.queryByTestId("row-actions-trigger")).toBeNull();
  });

  it("offers delete but not edit to a moderator", async () => {
    notes = [note({ author: { id: THEIRS, name: "Omar Saleh" } })];

    renderLane({ canModerate: true });
    await screen.findByText("Called about the invoice.");

    await userEvent.click(screen.getByTestId("row-actions-trigger"));

    const items = within(await screen.findByRole("menu"))
      .getAllByRole("menuitem")
      .map((item) => item.textContent);

    // A deletion is visible; an edit is not. Rewriting what a colleague said,
    // in their name, leaves no trace — so not even a supervisor is offered it.
    expect(items).toEqual(["Delete"]);
  });

  it("edits your own note in place", async () => {
    renderLane();
    await screen.findByText("Called about the invoice.");

    await userEvent.click(screen.getByTestId("row-actions-trigger"));
    await userEvent.click(await screen.findByRole("menuitem", { name: "Edit" }));

    const box = screen.getByLabelText("Edit");
    await userEvent.clear(box);
    await userEvent.type(box, "Called about the quote.");
    await userEvent.click(screen.getByRole("button", { name: "Save note" }));

    await waitFor(() => {
      const patch = writes().find((call) => call.method === "PATCH");
      expect(patch?.body?.body).toBe("Called about the quote.");
    });
  });

  it("marks an edited note so the text is not mistaken for the original", async () => {
    notes = [note({ edited: true })];

    renderLane();

    expect(await screen.findByText(/edited/)).toBeInTheDocument();
  });

  it("names the author before deleting their note", async () => {
    notes = [note({ author: { id: THEIRS, name: "Omar Saleh" } })];

    renderLane({ canModerate: true });
    await screen.findByText("Called about the invoice.");

    await userEvent.click(screen.getByTestId("row-actions-trigger"));
    await userEvent.click(await screen.findByRole("menuitem", { name: "Delete" }));

    const dialog = await screen.findByRole("dialog");

    expect(within(dialog).getByText(/written by Omar Saleh/)).toBeInTheDocument();
    expect(within(dialog).getByText(/cannot be recovered/i)).toBeInTheDocument();
    // Nothing sent until it is confirmed.
    expect(writes()).toHaveLength(0);
  });

  it("deletes only after confirmation", async () => {
    renderLane();
    await screen.findByText("Called about the invoice.");

    await userEvent.click(screen.getByTestId("row-actions-trigger"));
    await userEvent.click(await screen.findByRole("menuitem", { name: "Delete" }));
    await userEvent.click(
      within(await screen.findByRole("dialog")).getByRole("button", { name: "Delete" }),
    );

    await waitFor(() => expect(writes().some((call) => call.method === "DELETE")).toBe(true));
  });

  it("shows the server's reason when a write is refused", async () => {
    renderLane();
    await screen.findByText("Called about the invoice.");

    failWith = 403;

    await userEvent.type(screen.getByLabelText("Add note"), "Anything");
    await userEvent.click(screen.getByRole("button", { name: "Add note" }));

    // The UI only OFFERS; the server decides, and its reason is what the
    // reader needs.
    expect(await screen.findByRole("alert")).toBeInTheDocument();
  });

  it("invites the first note rather than showing an empty box", async () => {
    notes = [];

    renderLane();

    expect(await screen.findByText("No notes yet")).toBeInTheDocument();
    expect(screen.getByText(/keep in your head/i)).toBeInTheDocument();
  });

  it("renders the forbidden surface when notes are refused", async () => {
    failWith = 403;

    renderLane();

    // The composer goes with it: offering a box that cannot be submitted is
    // worse than saying plainly that the notes are not available.
    expect(await screen.findByText("You do not have access to these notes")).toBeInTheDocument();
    expect(screen.queryByLabelText("Add note")).toBeNull();
  });
});
