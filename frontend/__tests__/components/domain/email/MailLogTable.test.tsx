import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/admin/email",
}));

import { MailLogTable } from "@/components/domain/MailLogTable/MailLogTable";
import en from "@/messages/en.json";

const ROWS = [
  {
    id: "01L1",
    direction: "outbound",
    provider: "smtp",
    address: "hana@example.test",
    subject: "[#TKT-000042] Invoice is wrong",
    status: "failed",
    attempt: 3,
    duration_ms: 812,
    error: "550 Recipient address rejected",
    provider_code: "550",
    ticket_id: "01T1",
    message_id: "01M1",
    occurred_at: "2026-09-03T09:00:00Z",
  },
];

let urls: string[] = [];
let rows = ROWS;

beforeEach(() => {
  urls = [];
  rows = ROWS;

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      urls.push(String(input));

      return new Response(JSON.stringify({ data: rows, meta: { total: rows.length } }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

describe("the mail log", () => {
  it("shows what happened to a message", async () => {
    render(<MailLogTable />);

    expect(await screen.findByText("hana@example.test")).toBeInTheDocument();
    expect(screen.getByText("failed")).toBeInTheDocument();
  });

  it("shows the provider's own diagnosis", async () => {
    render(<MailLogTable />);

    // A generic "failed" gives an administrator nothing to act on.
    expect(await screen.findByText("550 Recipient address rejected")).toBeInTheDocument();
  });

  it("shows how long the provider took", async () => {
    render(<MailLogTable />);

    // A number that climbs is the first sign of trouble, and it is invisible
    // without this column.
    expect(await screen.findByText("812 ms")).toBeInTheDocument();
  });

  it("keeps an address readable in either writing direction", async () => {
    render(<MailLogTable />, { locale: "ar" });

    const address = await screen.findByText("hana@example.test");

    expect(address.getAttribute("dir")).toBe("ltr");
  });

  it("narrows to what did not go out", async () => {
    render(<MailLogTable />);
    await screen.findByText("hana@example.test");

    await userEvent.click(screen.getByRole("button", { name: en.admin.email.logFilter.failed }));

    // The filter people actually reach for, as one control rather than
    // something to construct.
    await waitFor(() => expect(urls.some((u) => u.includes("status=failed"))).toBe(true));
  });

  it("says nothing has been sent rather than showing a bare table", async () => {
    rows = [];
    render(<MailLogTable />);

    expect(await screen.findByText(en.admin.email.logEmpty)).toBeInTheDocument();
  });
});
