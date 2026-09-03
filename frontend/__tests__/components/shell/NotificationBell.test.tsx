import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/",
}));

import { NotificationBell } from "@/components/shell/NotificationBell";

import { ar, en, withIntl } from "./helpers";

import type { NotificationRow } from "@/lib/api/tickets";

function row(overrides: Partial<NotificationRow> = {}): NotificationRow {
  return {
    id: "1",
    text: "Ticket assigned to you",
    reference: null,
    ticket_id: "01T1",
    kind: "notifications.assigned",
    read: false,
    created_at: "2026-09-03T09:00:00Z",
    ...overrides,
  };
}

const ITEMS: NotificationRow[] = [
  row({ id: "1", reference: "TKT-000123" }),
  row({ id: "2", text: "SLA at risk", read: true }),
  row({ id: "3", text: "Customer replied" }),
];

describe("NotificationBell", () => {
  it("carries a translated accessible name", () => {
    render(withIntl(<NotificationBell />));

    expect(
      screen.getByRole("button", { name: en.shell.actions.openNotifications }),
    ).toBeInTheDocument();
  });

  it("translates the accessible name into Arabic", () => {
    render(withIntl(<NotificationBell />, "ar"));

    expect(
      screen.getByRole("button", { name: ar.shell.actions.openNotifications }),
    ).toBeInTheDocument();
  });

  it("shows the empty state when there is nothing to report", async () => {
    const user = userEvent.setup();
    render(withIntl(<NotificationBell />));

    await user.click(screen.getByTestId("notification-bell"));

    expect(await screen.findByText(en.shell.notifications.empty)).toBeInTheDocument();
    expect(screen.queryByTestId("notification-list")).toBeNull();
  });

  it("renders the items as a plain list", async () => {
    const user = userEvent.setup();
    render(withIntl(<NotificationBell items={ITEMS} />));

    await user.click(screen.getByTestId("notification-bell"));

    const list = await screen.findByTestId("notification-list");
    expect(list.tagName).toBe("UL");
    expect(screen.getAllByRole("listitem")).toHaveLength(3);
    expect(screen.queryByText(en.shell.notifications.empty)).toBeNull();
  });

  it("isolates a ticket reference so it cannot reorder in Arabic", async () => {
    const user = userEvent.setup();
    render(withIntl(<NotificationBell items={ITEMS} />, "ar"));

    await user.click(screen.getByTestId("notification-bell"));

    const reference = await screen.findByText("TKT-000123");
    expect(reference.tagName).toBe("BDI");
    expect(reference).toHaveAttribute("dir", "ltr");
  });

  it("shows the unread count, now that there is a real one", async () => {
    render(withIntl(<NotificationBell items={ITEMS} unreadCount={2} />));

    /*
     * Story 1.3 deliberately shipped no badge, on the grounds that a number the
     * product could not honour would be a lie. There is a real count now — from
     * a single indexed query — so the badge is honest and the reason is met.
     */
    expect(screen.getByTestId("notification-bell")).toHaveTextContent("2");
  });

  it("counts the unread, not the length of the list", async () => {
    // The list is capped at twenty server-side; a badge reading "3" when there
    // were ninety would be worse than none, because it would look precise.
    render(withIntl(<NotificationBell items={ITEMS} unreadCount={90} />));

    expect(screen.getByTestId("notification-bell")).toHaveTextContent("9+");
  });

  it("puts the count in the accessible name, not only in the badge", () => {
    render(withIntl(<NotificationBell items={ITEMS} unreadCount={2} />));

    // A screen reader hearing a bare "2" next to "Open notifications" learns
    // nothing about what the 2 counts.
    expect(screen.getByRole("button", { name: /2/ })).toBeInTheDocument();
  });

  it("shows no badge when there is nothing unread", () => {
    render(withIntl(<NotificationBell items={ITEMS} unreadCount={0} />));

    expect(screen.getByTestId("notification-bell")).toHaveTextContent("");
  });

  it("distinguishes read from unread by weight, not colour alone", async () => {
    const user = userEvent.setup();
    render(withIntl(<NotificationBell items={ITEMS} unreadCount={2} />));

    await user.click(screen.getByTestId("notification-bell"));

    const rows = await screen.findAllByRole("listitem");

    // This list is scanned at a glance, and colour alone does not survive
    // greyscale.
    expect(rows[0]).toHaveAttribute("data-read", "false");
    expect(rows[1]).toHaveAttribute("data-read", "true");
  });

  it("opens the ticket a notification is about", async () => {
    const user = userEvent.setup();
    const onOpenTicket = vi.fn();

    render(withIntl(<NotificationBell items={ITEMS} onOpenTicket={onOpenTicket} />));

    await user.click(screen.getByTestId("notification-bell"));
    await user.click(await screen.findByRole("button", { name: /Ticket assigned to you/ }));

    // A notification you cannot act on just makes somebody go and find the
    // ticket by hand.
    expect(onOpenTicket).toHaveBeenCalledWith("01T1");
  });

  it("drops the badge as soon as an item is opened", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => new Response(null, { status: 204 })),
    );

    const user = userEvent.setup();
    render(withIntl(<NotificationBell items={ITEMS} unreadCount={2} />));

    await user.click(screen.getByTestId("notification-bell"));
    await user.click(await screen.findByRole("button", { name: /Ticket assigned to you/ }));

    /*
     * Optimistically. The badge dropping the instant somebody looks is what
     * they expect; waiting for a round trip makes the bell feel broken, and a
     * failure costs nothing because the next refetch puts the count back.
     */
    expect(screen.getByTestId("notification-bell")).toHaveTextContent("1");

    vi.unstubAllGlobals();
  });
});
