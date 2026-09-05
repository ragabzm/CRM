import { render, screen, withIntl } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { SatisfactionPrompt } from "@/components/domain/SatisfactionPrompt/SatisfactionPrompt";
import en from "@/messages/en.json";

/**
 * Two answers, and no third.
 *
 * Most of these tests are about what is NOT on the screen: no stars, no scale,
 * no number, and no colour carrying meaning on its own. Each of those is a
 * thing somebody would add in good faith, and each would make the figure
 * behind it something that needs converting before it can be compared.
 */

describe("saying how it went", () => {
  it("offers exactly two choices and no number anywhere", () => {
    const { container } = render(
      <SatisfactionPrompt
        satisfaction={null}
        satisfactionComment={null}
        canRate
        onRate={vi.fn()}
      />,
    );

    const choices = container.querySelectorAll("[data-slot='satisfaction-choice']");

    expect(choices).toHaveLength(2);

    /*
     * No stars, no 1–5, no percentage. A digit on this screen is the first
     * step towards a scale that then needs a midpoint and a conversion.
     */
    expect(container.textContent).not.toMatch(/[0-9]/);
  });

  it("labels both controls in words, not by colour or icon alone", () => {
    render(
      <SatisfactionPrompt
        satisfaction={null}
        satisfactionComment={null}
        canRate
        onRate={vi.fn()}
      />,
    );

    /*
     * UX-03: distinguishable in greyscale. A colour is invisible to somebody
     * who cannot tell the two apart, and an icon alone is announced as
     * nothing at all by a screen reader.
     */
    expect(screen.getByRole("button", { name: en.portal.feedback.good })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: en.portal.feedback.bad })).toBeInTheDocument();
  });

  it("records the answer in one tap, with no comment", async () => {
    const onRate = vi.fn().mockResolvedValue(undefined);

    render(
      <SatisfactionPrompt satisfaction={null} satisfactionComment={null} canRate onRate={onRate} />,
    );

    await userEvent.click(screen.getByRole("button", { name: en.portal.feedback.good }));

    // One tap is a complete answer. `undefined`, not an empty string — nothing
    // was written and nothing should be stored.
    expect(onRate).toHaveBeenCalledWith(true, undefined);
  });

  it("offers the comment only after the rating, never before", () => {
    const { rerender } = render(
      <SatisfactionPrompt
        satisfaction={null}
        satisfactionComment={null}
        canRate
        onRate={vi.fn()}
      />,
    );

    // Nothing to write in until they have answered.
    expect(screen.queryByText(en.portal.feedback.addComment)).toBeNull();
    expect(screen.queryByRole("textbox")).toBeNull();

    rerender(
      withIntl(
        <SatisfactionPrompt
          satisfaction={true}
          satisfactionComment={null}
          canRate
          onRate={vi.fn()}
        />,
      ),
    );

    expect(screen.getByText(en.portal.feedback.addComment)).toBeInTheDocument();
  });

  it("marks the chosen answer without relying on colour", () => {
    const { container } = render(
      <SatisfactionPrompt
        satisfaction={false}
        satisfactionComment={null}
        canRate
        onRate={vi.fn()}
      />,
    );

    const [good, bad] = [...container.querySelectorAll("[data-slot='satisfaction-choice']")];

    // `aria-pressed` carries it for anybody not looking at the screen at all.
    expect(good!.getAttribute("aria-pressed")).toBe("false");
    expect(bad!.getAttribute("aria-pressed")).toBe("true");
  });

  it("says the answer is locked rather than ignoring the tap", async () => {
    const onRate = vi.fn();

    render(
      <SatisfactionPrompt
        satisfaction={true}
        satisfactionComment={null}
        canRate={false}
        onRate={onRate}
      />,
    );

    /*
     * A control that silently does nothing makes somebody tap again and then
     * conclude the product is broken — and they would be right.
     */
    expect(screen.getByText(en.portal.feedback.locked)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: en.portal.feedback.good })).toBeDisabled();

    await userEvent.click(screen.getByRole("button", { name: en.portal.feedback.bad }));
    expect(onRate).not.toHaveBeenCalled();
  });

  it("shows what they wrote once it can no longer be changed", () => {
    render(
      <SatisfactionPrompt
        satisfaction={false}
        satisfactionComment="It took four days."
        canRate={false}
        onRate={vi.fn()}
      />,
    );

    expect(screen.getByText("It took four days.")).toBeInTheDocument();
  });
});
