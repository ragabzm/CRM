import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { FileInput } from "@/components/ui/file-input";
import { render, screen } from "@/__tests__/helpers/intl";

describe("FileInput carries the mobile capture path", () => {
  it("forwards accept to the DOM, so the picker is filtered", () => {
    render(<FileInput label="Attach a photo" accept="image/*" />);

    expect(screen.getByLabelText("Attach a photo")).toHaveAttribute("accept", "image/*");
  });

  it.each(["environment", "user"] as const)("forwards capture=%s", (capture) => {
    render(<FileInput label="Attach a photo" accept="image/*" capture={capture} />);

    // This attribute is the entire mobile camera story; a custom dropzone
    // cannot express it.
    expect(screen.getByLabelText("Attach a photo")).toHaveAttribute("capture", capture);
  });

  it("is a real file input", () => {
    render(<FileInput label="Attach" />);

    expect(screen.getByLabelText("Attach")).toHaveAttribute("type", "file");
  });

  it("forwards multiple, name and disabled", () => {
    render(<FileInput label="Attach" name="evidence" multiple disabled />);

    const input = screen.getByLabelText("Attach");
    expect(input).toHaveAttribute("name", "evidence");
    expect(input).toHaveAttribute("multiple");
    expect(input).toBeDisabled();
  });
});

describe("FileInput is reachable without a pointer", () => {
  it("associates the label with the input", () => {
    const { container } = render(<FileInput label="Attach" />);

    const label = container.querySelector("label")!;
    const input = screen.getByLabelText("Attach");

    expect(label).toHaveAttribute("for", input.id);
    expect(input.id).toBeTruthy();
  });

  it("keeps the input in the tab order rather than hiding it", async () => {
    const user = userEvent.setup();
    render(<FileInput label="Attach" />);

    await user.tab();

    // sr-only, never display:none — a hidden input is unreachable by keyboard
    // and invisible to assistive technology.
    const input = screen.getByLabelText("Attach");
    expect(input).toHaveFocus();
    expect(input.className).toContain("sr-only");
    expect(input.className).not.toContain("hidden");
  });

  it("activates the input when the label is used, so the whole target is tappable", async () => {
    const user = userEvent.setup();
    const { container } = render(<FileInput label="Attach" />);

    const input = screen.getByLabelText("Attach");
    // A listener, not a spy on .click(): label activation dispatches a click
    // event at the control rather than calling the method.
    const onClick = vi.fn();
    input.addEventListener("click", onClick);

    await user.click(container.querySelector("label")!);

    // The label WRAPS the control, which is what makes the whole row a target
    // at 390px where the input itself is a thin strip.
    expect(onClick).toHaveBeenCalled();
  });

  /*
   * Pressing Enter on a focused file input opens the picker in every real
   * browser, but jsdom does not implement that activation behaviour — there is
   * nothing to assert here that would not be asserting jsdom. What a test can
   * pin is that the control is focusable and label-activated (above); the
   * picker itself is covered by the manual device pass in the Done Criteria.
   */

  it("draws its focus indicator from the token", () => {
    const { container } = render(<FileInput label="Attach" />);

    // focus-within, because focus sits on the input while the label is what the
    // user sees.
    expect(container.querySelector("label")!.className).toContain(
      "focus-within:outline-(--focus-ring)",
    );
  });

  it("wires a description through aria-describedby", () => {
    render(<FileInput label="Attach" description="PNG or JPEG, up to 10 MB." />);

    const input = screen.getByLabelText("Attach");
    const describedBy = input.getAttribute("aria-describedby");

    expect(describedBy).toBeTruthy();
    expect(document.getElementById(describedBy!)).toHaveTextContent("PNG or JPEG");
  });

  it("gives two inputs on one page distinct ids", () => {
    render(
      <>
        <FileInput label="Front" />
        <FileInput label="Back" />
      </>,
    );

    expect(screen.getByLabelText("Front").id).not.toBe(screen.getByLabelText("Back").id);
  });
});
