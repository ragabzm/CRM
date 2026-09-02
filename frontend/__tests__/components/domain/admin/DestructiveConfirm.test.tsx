import { render, screen } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";

import { DestructiveConfirm } from "@/components/domain/DestructiveConfirm/DestructiveConfirm";

afterEach(() => vi.restoreAllMocks());

const CONSEQUENCE = "This will delete the category “Billing”.";

describe("DestructiveConfirm states the consequence", () => {
  it("shows what will actually happen, not a generic question", () => {
    render(
      <DestructiveConfirm
        open
        onOpenChange={vi.fn()}
        consequence={CONSEQUENCE}
        confirmLabel="Delete"
        onConfirm={vi.fn()}
      />,
    );

    expect(screen.getByText(CONSEQUENCE)).toBeInTheDocument();
    // The words a reader learns to click past.
    expect(screen.queryByText(/are you sure/i)).not.toBeInTheDocument();
  });

  it("refuses to render at all without consequence text", () => {
    // Not "falls back to a generic prompt": a fallback would let a caller ship
    // the exact dialog this component exists to prevent, and it would look
    // correct in review.
    const consoleError = vi.spyOn(console, "error").mockImplementation(() => undefined);

    const { container } = render(
      <DestructiveConfirm
        open
        onOpenChange={vi.fn()}
        consequence=""
        confirmLabel="Delete"
        onConfirm={vi.fn()}
      />,
    );

    expect(container).toBeEmptyDOMElement();
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    expect(consoleError).toHaveBeenCalled();
  });

  it("treats whitespace as no consequence at all", () => {
    vi.spyOn(console, "error").mockImplementation(() => undefined);

    render(
      <DestructiveConfirm
        open
        onOpenChange={vi.fn()}
        consequence="   "
        confirmLabel="Delete"
        onConfirm={vi.fn()}
      />,
    );

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("confirms only when the destructive button is pressed", async () => {
    const onConfirm = vi.fn();
    const onOpenChange = vi.fn();

    render(
      <DestructiveConfirm
        open
        onOpenChange={onOpenChange}
        consequence={CONSEQUENCE}
        confirmLabel="Delete"
        onConfirm={onConfirm}
      />,
    );

    await userEvent.click(screen.getByRole("button", { name: "Cancel" }));
    expect(onConfirm).not.toHaveBeenCalled();
    expect(onOpenChange).toHaveBeenCalledWith(false);

    await userEvent.click(screen.getByRole("button", { name: "Delete" }));
    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  it("renders nothing while closed", () => {
    render(
      <DestructiveConfirm
        open={false}
        onOpenChange={vi.fn()}
        consequence={CONSEQUENCE}
        confirmLabel="Delete"
        onConfirm={vi.fn()}
      />,
    );

    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });
});
