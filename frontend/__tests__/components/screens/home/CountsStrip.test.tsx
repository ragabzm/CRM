import { render, screen, within } from "@/__tests__/helpers/intl";
import { describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/",
}));

import { CountsStrip } from "@/components/screens/home/CountsStrip";
import en from "@/messages/en.json";

/**
 * Five numbers, five links.
 *
 * The load-bearing property: the URL a tile points at IS the filter the number
 * was counted with. A figure that opens a list with a different number of rows
 * is worse than no figure, because the agent trusted it.
 */

const COUNTS = {
  assigned_to_me: 4,
  unassigned: 7,
  at_risk: null,
  breached: null,
  pending_customer_reply: 2,
};

const renderStrip = (counts = COUNTS, userId: number | null = 7) =>
  render(<CountsStrip counts={counts} currentUserId={userId} />);

const tile = (key: string) => document.querySelector(`[data-count="${key}"]`) as HTMLAnchorElement;

describe("the counts strip", () => {
  it("shows all five", () => {
    renderStrip();

    for (const label of [
      en.home.counts.assignedToMe,
      en.home.counts.unassigned,
      en.home.counts.atRisk,
      en.home.counts.breached,
      en.home.counts.pendingCustomerReply,
    ]) {
      expect(screen.getByText(label)).toBeInTheDocument();
    }
  });

  it("makes every tile a real link", () => {
    renderStrip();

    // Navigation, not a button with a router push: it should open in a new tab
    // on a middle click, be copyable, and be keyboard-reachable without any of
    // that being re-implemented.
    const links = screen.getAllByRole("link");

    expect(links).toHaveLength(5);
  });

  it("links assigned-to-me to that agent's live tickets", () => {
    renderStrip();

    expect(tile("assignedToMe").getAttribute("href")).toBe(
      "/tickets?status=open%2Cpending&assignee_id=7",
    );
  });

  it("links unassigned to the pool", () => {
    renderStrip();

    // The sentinel, not an empty value: "unassigned" is a real answer, and an
    // absent filter would list everything.
    expect(tile("unassigned").getAttribute("href")).toContain("assignee_id=unassigned");
  });

  it("links waiting-on-the-customer to that status alone", () => {
    renderStrip();

    expect(tile("pendingCustomerReply").getAttribute("href")).toBe("/tickets?status=pending");
  });

  it("shows a dash for a count nothing tracks yet", () => {
    renderStrip();

    /*
     * A dash, not a zero. There is no SLA module yet, so "no ticket is at
     * risk" is a claim this system cannot make — and an agent who read a
     * confident zero would stop looking.
     */
    expect(within(tile("atRisk")).getByText("—")).toBeInTheDocument();
    expect(within(tile("breached")).getByText("—")).toBeInTheDocument();
  });

  it("says why the dash is there", () => {
    renderStrip();

    // An unexplained dash reads as a bug.
    expect(within(tile("atRisk")).getByText(en.home.counts.notKnownHint)).toBeInTheDocument();
  });

  it("keeps the labels in place before any value arrives", () => {
    renderStrip(null as never);

    // No layout shift when the numbers land, and nothing invented meanwhile.
    expect(screen.getAllByRole("link")).toHaveLength(5);
    expect(screen.getAllByText("—")).toHaveLength(5);
  });

  it("omits the assignee filter when nobody is signed in", () => {
    renderStrip(COUNTS, null);

    // `assignee_id=null` would be a filter matching nothing, presented as if
    // it were the agent's own work.
    expect(tile("assignedToMe").getAttribute("href")).not.toContain("assignee_id");
  });

  it("renders the figures with the locale's own numerals", () => {
    renderStrip();

    expect(within(tile("unassigned")).getByText("7")).toBeInTheDocument();
  });
});
