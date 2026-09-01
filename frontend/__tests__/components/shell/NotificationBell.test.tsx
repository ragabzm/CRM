import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/",
}));

import { NotificationBell } from "@/components/shell/NotificationBell";

import { ar, en, withIntl } from "./helpers";

const ITEMS = [
  { id: "1", text: "Ticket assigned to you", reference: "TKT-000123" },
  { id: "2", text: "SLA at risk" },
  { id: "3", text: "Customer replied" },
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

  it("shows no unread badge — a count it cannot honour would be a lie", async () => {
    const user = userEvent.setup();
    render(withIntl(<NotificationBell items={ITEMS} />));

    const trigger = screen.getByTestId("notification-bell");
    expect(trigger.textContent).toBe("");

    await user.click(trigger);
    expect(await screen.findByTestId("notification-list")).toBeInTheDocument();
  });
});
