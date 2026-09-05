import { render, screen } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { FeedbackLandingScreen } from "@/components/screens/portal/FeedbackLandingScreen";
import en from "@/messages/en.json";

const search = { current: new URLSearchParams() };
const rateByInvitation = vi.fn();

vi.mock("next/navigation", () => ({
  useSearchParams: () => search.current,
}));

vi.mock("@/lib/portal/api", () => ({
  rateByInvitation: (...args: unknown[]) => rateByInvitation(...args),
}));

/**
 * The page a tap in the resolution email lands on.
 *
 * The answer is already recorded when this renders. What is being tested is
 * the second half: that the comment is offered, that it is optional, and that
 * nothing here needs — or grants — a sign-in.
 */
describe("landing from the email", () => {
  beforeEach(() => {
    rateByInvitation.mockReset();
    rateByInvitation.mockResolvedValue({ satisfaction: true, satisfaction_comment: null });
    search.current = new URLSearchParams({
      ticket: "01JQZ0000000000000000000AA",
      verdict: "up",
      expires: "1790000000",
      signature: "abc123",
    });
  });

  it("shows the answer as already recorded", () => {
    render(<FeedbackLandingScreen />);

    expect(screen.getByText(en.portal.feedback.landingTitle)).toBeInTheDocument();
    // Reflected back, so somebody who tapped the wrong one sees it immediately.
    expect(screen.getByRole("button", { name: en.portal.feedback.good })).toHaveAttribute(
      "aria-pressed",
      "true",
    );
  });

  it("offers the comment and sends it with the signature untouched", async () => {
    render(<FeedbackLandingScreen />);

    await userEvent.click(screen.getByText(en.portal.feedback.addComment));
    await userEvent.type(screen.getByRole("textbox"), "Quick and clear.");
    await userEvent.click(screen.getByRole("button", { name: en.portal.feedback.saveComment }));

    expect(rateByInvitation).toHaveBeenCalledWith(
      {
        ticket: "01JQZ0000000000000000000AA",
        verdict: "up",
        expires: "1790000000",
        signature: "abc123",
      },
      "Quick and clear.",
    );
  });

  it("changes the answer through the same link", async () => {
    rateByInvitation.mockResolvedValue({ satisfaction: false, satisfaction_comment: null });

    render(<FeedbackLandingScreen />);

    await userEvent.click(screen.getByRole("button", { name: en.portal.feedback.bad }));

    // The verdict travels in the path, so changing the answer changes it too.
    expect(rateByInvitation).toHaveBeenCalledWith(
      expect.objectContaining({ verdict: "down" }),
      undefined,
    );
  });

  it("says so plainly when the link arrived without its signature", () => {
    search.current = new URLSearchParams({ ticket: "01JQZ0000000000000000000AA", verdict: "up" });

    render(<FeedbackLandingScreen />);

    /*
     * A mail client that wrapped the URL. The customer did nothing wrong, and
     * a blank page would read as the product ignoring them.
     */
    expect(screen.getByText(en.portal.feedback.linkBrokenTitle)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: en.portal.feedback.good })).toBeNull();
  });
});
