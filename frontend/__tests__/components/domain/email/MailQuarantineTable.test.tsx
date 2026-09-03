import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/admin/email",
}));

import { MailQuarantineTable } from "@/components/domain/MailQuarantine/MailQuarantineTable";
import en from "@/messages/en.json";

/**
 * The only surface on which a silently-lost customer email is visible.
 */

const ROW = {
  id: "01Q1",
  provider: "generic",
  from_address: "hana@example.test",
  subject: "Invoice trouble",
  reason: "The message has no readable From address.",
  received_at: "2026-09-03T09:00:00Z",
  resolved_at: null,
  raw_bytes: 2048,
};

let urls: string[] = [];
let rows: unknown[] = [ROW];
let replayStatus = "accepted";

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  urls = [];
  rows = [ROW];
  replayStatus = "accepted";

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      urls.push(`${init?.method ?? "GET"} ${url}`);

      if (url.includes("/sanctum/csrf-cookie")) return new Response(null, { status: 204 });
      if (url.includes("/replay")) return json({ status: replayStatus, reason: "still broken" });
      if (/quarantine\/[^/?]+$/.test(url)) {
        return json({ ...ROW, raw: "From: nobody\r\nSubject: Invoice trouble\r\n\r\nBody" });
      }

      return json({ data: rows, meta: { total: rows.length } });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const openMenu = async () => {
  await userEvent.click(await screen.findByRole("button", { name: /Invoice trouble/ }));
};

describe("the quarantine list", () => {
  it("shows what could not be handled", async () => {
    render(<MailQuarantineTable />);

    expect(await screen.findByText("Invoice trouble")).toBeInTheDocument();
    expect(screen.getByText(ROW.reason)).toBeInTheDocument();
  });

  it("shows only outstanding messages by default", async () => {
    render(<MailQuarantineTable />);

    await waitFor(() => expect(urls).not.toHaveLength(0));

    // A list that mixes handled and unhandled becomes a list nobody reads to
    // the bottom of.
    expect(urls[0]).toContain("resolved=false");
  });

  it("can show everything when asked", async () => {
    render(<MailQuarantineTable />);
    await screen.findByText("Invoice trouble");

    await userEvent.click(screen.getByRole("button", { name: en.admin.email.quarantineAll }));

    await waitFor(() =>
      expect(urls.some((u) => u.includes("/quarantine") && !u.includes("resolved=false"))).toBe(
        true,
      ),
    );
  });

  it("says a message still needs a look", async () => {
    render(<MailQuarantineTable />);

    expect(await screen.findByText(en.admin.email.quarantineUnresolved)).toBeInTheDocument();
  });

  it("offers a replay, which is the point of keeping the bytes", async () => {
    render(<MailQuarantineTable />);
    await openMenu();

    await userEvent.click(await screen.findByRole("menuitem", { name: en.admin.email.replay }));

    await waitFor(() => expect(urls.some((u) => u.includes("/replay"))).toBe(true));
    // The table itself carries a status region, so the banner is found by
    // its text rather than by role alone.
    expect(await screen.findByText(en.admin.email.replayed)).toBeInTheDocument();
  });

  it("says plainly when a replay still fails", async () => {
    replayStatus = "quarantined";
    render(<MailQuarantineTable />);
    await openMenu();

    await userEvent.click(await screen.findByRole("menuitem", { name: en.admin.email.replay }));

    /*
     * Reported as a failure, not a success. The message is still not on
     * anybody's desk, and a green tick would tell the administrator to stop
     * looking.
     */
    expect(await screen.findByRole("alert")).toHaveTextContent(en.admin.email.replayFailed);
  });

  it("shows the raw source only when asked for it", async () => {
    render(<MailQuarantineTable />);
    await openMenu();

    await userEvent.click(await screen.findByRole("menuitem", { name: en.admin.email.viewRaw }));

    // A customer's entire email. Fetched one at a time, deliberately — a list
    // view has no reason to carry twenty of them.
    expect(await screen.findByText(/Subject: Invoice trouble/)).toBeInTheDocument();
  });

  it("renders the source without reflowing it", async () => {
    render(<MailQuarantineTable />);
    await openMenu();
    await userEvent.click(await screen.findByRole("menuitem", { name: en.admin.email.viewRaw }));

    const block = await screen.findByText(/Subject: Invoice trouble/);

    // These are RFC 5322 bytes, not prose: reflowing hides exactly the folded
    // header somebody opened this to find.
    expect(block.tagName).toBe("PRE");
    expect(block.getAttribute("dir")).toBe("ltr");
  });

  it("does not offer a replay for something already handled", async () => {
    rows = [{ ...ROW, resolved_at: "2026-09-03T10:00:00Z" }];
    render(<MailQuarantineTable />);

    await openMenu();

    // A second replay would create a second ticket for one email.
    expect(screen.queryByRole("menuitem", { name: en.admin.email.replay })).toBeNull();
    expect(
      await screen.findByRole("menuitem", { name: en.admin.email.viewRaw }),
    ).toBeInTheDocument();
  });

  it("says nothing is waiting rather than showing a bare table", async () => {
    rows = [];
    render(<MailQuarantineTable />);

    expect(await screen.findByText(en.admin.email.quarantineEmpty)).toBeInTheDocument();
  });
});
