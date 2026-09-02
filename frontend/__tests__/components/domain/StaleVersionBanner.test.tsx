import { render, screen, ar, en } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { StaleVersionBanner } from "@/components/domain/StaleVersionBanner/StaleVersionBanner";

describe("the stale version banner", () => {
  it("says plainly what happened", () => {
    render(<StaleVersionBanner onReload={vi.fn()} />);

    expect(screen.getByRole("alert")).toHaveTextContent(en.tickets.staleVersion.message);
    // And what to do about it, so the reader is not left holding an error.
    expect(screen.getByRole("alert")).toHaveTextContent(en.tickets.staleVersion.detail);
  });

  it("offers exactly one way forward", () => {
    render(<StaleVersionBanner onReload={vi.fn()} />);

    const buttons = screen.getAllByRole("button");

    // One button. A per-field merge asks someone to adjudicate a conflict they
    // have no context for, while a customer waits.
    expect(buttons).toHaveLength(1);
    expect(buttons[0]).toHaveTextContent(en.tickets.staleVersion.reload);
  });

  it("reloads when asked", async () => {
    const onReload = vi.fn();

    render(<StaleVersionBanner onReload={onReload} />);
    await userEvent.click(screen.getByRole("button", { name: en.tickets.staleVersion.reload }));

    expect(onReload).toHaveBeenCalledTimes(1);
  });

  it("cannot be pressed twice while reloading", async () => {
    const onReload = vi.fn();

    render(<StaleVersionBanner onReload={onReload} busy />);
    await userEvent.click(screen.getByRole("button", { name: en.tickets.staleVersion.reload }));

    expect(onReload).not.toHaveBeenCalled();
  });

  it("is announced immediately, not politely", () => {
    render(<StaleVersionBanner onReload={vi.fn()} />);

    // The reader is about to save over someone else's change; this cannot wait
    // for a pause in the screen reader's queue.
    expect(screen.getByRole("alert")).toBeInTheDocument();
  });

  it("renders in Arabic", () => {
    render(<StaleVersionBanner onReload={vi.fn()} />, { locale: "ar" });

    expect(screen.getByRole("alert")).toHaveTextContent(ar.tickets.staleVersion.message);
    expect(
      screen.getByRole("button", { name: ar.tickets.staleVersion.reload }),
    ).toBeInTheDocument();
  });
});
