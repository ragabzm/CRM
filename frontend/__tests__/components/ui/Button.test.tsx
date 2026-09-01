import { render, screen } from "@/__tests__/helpers/intl";
import { describe, expect, it } from "vitest";

import { Button, type ButtonVariant } from "@/components/ui/button";

const VARIANTS: ButtonVariant[] = ["primary", "secondary", "ghost", "destructive"];

/**
 * UX-03 / NFR-13: a state that carries meaning must survive greyscale. These
 * tests assert each variant differs by something that is not hue.
 */
describe("Button variants survive greyscale", () => {
  it.each(VARIANTS)("%s renders and is identifiable", (variant) => {
    render(<Button variant={variant}>Act</Button>);

    const button = screen.getByRole("button", { name: /act/i });
    expect(button).toBeInTheDocument();
    expect(button).toHaveAttribute("data-variant", variant);
  });

  it("gives every variant a distinct non-colour signature", () => {
    const signatures = VARIANTS.map((variant) => {
      const { container, unmount } = render(<Button variant={variant}>Act</Button>);
      const button = container.querySelector("button")!;

      // The non-colour half of the signal: font weight, border presence, and
      // whether a glyph is drawn.
      const classes = button.className;
      const weight = /font-(semibold|medium|bold|normal)/.exec(classes)?.[1] ?? "none";
      const bordered = /border-border-strong/.test(classes);
      const hasGlyph = button.querySelector("svg") !== null;

      unmount();
      return `${weight}|bordered:${bordered}|glyph:${hasGlyph}`;
    });

    // Each variant must differ from every other on at least one non-colour axis.
    for (let i = 0; i < signatures.length; i++) {
      for (let j = i + 1; j < signatures.length; j++) {
        if (signatures[i] === signatures[j]) {
          // Two variants sharing a signature would be distinguishable only by
          // hue, which fails under greyscale.
          expect(
            `${VARIANTS[i]} and ${VARIANTS[j]} share the signature ${signatures[i]}`,
          ).toBe("distinct non-colour signatures");
        }
      }
    }
  });

  it("gives destructive a warning glyph, so red is never the only cue", () => {
    const { container } = render(<Button variant="destructive">Delete</Button>);

    expect(container.querySelector("svg")).not.toBeNull();
  });

  it("lets a caller override the glyph without losing the variant", () => {
    const { container } = render(
      <Button variant="destructive" icon={<span data-testid="custom-glyph" />}>
        Delete
      </Button>,
    );

    expect(screen.getByTestId("custom-glyph")).toBeInTheDocument();
    expect(container.querySelector("button")).toHaveAttribute("data-variant", "destructive");
  });

  it("carries no colour literal in its class output", () => {
    const { container } = render(<Button variant="primary">Act</Button>);

    expect(container.querySelector("button")!.className).not.toMatch(/#[0-9a-fA-F]{3,8}|rgb|oklch/);
  });
});

describe("focus indicator comes from the token", () => {
  it("does not suppress the outline the base layer draws", () => {
    const { container } = render(<Button variant="primary">Act</Button>);

    // A blanket `outline-none` would beat the :focus-visible rule in @layer
    // base, because utilities are emitted after it — and the focus indicator
    // would silently vanish.
    const classes = container.querySelector("button")!.className;
    expect(classes).not.toMatch(/(^|\s)outline-none(\s|$)/);
    expect(classes).not.toMatch(/(^|\s)outline-hidden(\s|$)/);
  });

  it("declares no bespoke focus ring of its own", () => {
    const { container } = render(<Button variant="primary">Act</Button>);

    // One token, one rule, one appearance across every primitive.
    expect(container.querySelector("button")!.className).not.toMatch(/focus-visible:ring/);
  });

  it("is focusable by keyboard", async () => {
    render(<Button variant="primary">Act</Button>);

    const button = screen.getByRole("button", { name: /act/i });
    button.focus();

    expect(button).toHaveFocus();
  });
});
