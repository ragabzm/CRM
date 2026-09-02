import { render, screen } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { RowActions } from "@/components/domain/RowActions/RowActions";

const ACTIONS = [
  { id: "open", label: "Open ticket", onSelect: vi.fn() },
  { id: "assign", label: "Assign", onSelect: vi.fn() },
  { id: "delete", label: "Delete", onSelect: vi.fn(), separated: true, destructive: true },
];

/**
 * UX-02: the overflow control is persistent, never revealed on hover.
 *
 * Every test here renders and asserts WITHOUT dispatching a hover event — that
 * absence is the point of the test.
 */
describe("RowActions overflow trigger is persistent", () => {
  it("is in the DOM with no hover event ever fired", () => {
    render(<RowActions rowLabel="TKT-000123" actions={ACTIONS} />);

    expect(screen.getByTestId("row-actions-trigger")).toBeInTheDocument();
  });

  it("is visible, not merely present", () => {
    render(<RowActions rowLabel="TKT-000123" actions={ACTIONS} />);

    const trigger = screen.getByTestId("row-actions-trigger");
    const styles = getComputedStyle(trigger);

    expect(styles.visibility).not.toBe("hidden");
    expect(styles.display).not.toBe("none");
    expect(styles.opacity).not.toBe("0");
  });

  it("carries no hover- or focus-gated visibility classes", () => {
    render(<RowActions rowLabel="TKT-000123" actions={ACTIONS} />);

    const classes = screen.getByTestId("row-actions-trigger").className;

    // These are the exact patterns that turn a persistent control into a
    // hover-revealed one. A control that appears on hover does not exist for a
    // touch user and is invisible to anyone scanning the page.
    expect(classes).not.toMatch(/(^|\s)opacity-0(\s|$)/);
    expect(classes).not.toMatch(/(^|\s)invisible(\s|$)/);
    expect(classes).not.toMatch(/group-hover:/);
    expect(classes).not.toMatch(/group-focus-within:/);
    expect(classes).not.toMatch(/(^|\s)hidden(\s|$)/);
  });

  it("names the row it acts on, so the control is unambiguous to a screen reader", () => {
    render(<RowActions rowLabel="TKT-000123" actions={ACTIONS} />);

    expect(screen.getByRole("button", { name: /actions for TKT-000123/i })).toBeInTheDocument();
  });

  it("opens its menu from the keyboard alone", async () => {
    const user = userEvent.setup();
    render(<RowActions rowLabel="TKT-000123" actions={ACTIONS} />);

    await user.tab();
    expect(screen.getByTestId("row-actions-trigger")).toHaveFocus();

    await user.keyboard("{Enter}");

    expect(await screen.findByRole("menuitem", { name: /open ticket/i })).toBeInTheDocument();
  });

  it("separates secondary actions from primary ones", async () => {
    const user = userEvent.setup();
    render(<RowActions rowLabel="TKT-000123" actions={ACTIONS} />);

    await user.click(screen.getByTestId("row-actions-trigger"));

    expect(await screen.findByRole("menuitem", { name: /delete/i })).toBeInTheDocument();
    expect(screen.getByRole("separator")).toBeInTheDocument();
  });
});
