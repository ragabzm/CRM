import { describe, expect, it } from "vitest";

import { render } from "@/__tests__/helpers/intl";
import { Button } from "@/components/ui/button";
import { TOUCH_TARGET } from "@/lib/utils";

/**
 * Every control can be hit by a thumb.
 *
 * R-05 of the responsive design: 44x44 CSS px minimum, "applied at EVERY band,
 * because width does not predict input". A 1440px viewport may be a
 * touchscreen and a phone may be driven by a Bluetooth keyboard, so this is
 * not something that switches on below 768px.
 *
 * Measured at 390px, the top bar's controls were 24x24 and every filter was
 * 26px tall.
 *
 * jsdom computes no layout, so these assert the MECHANISM is attached rather
 * than the resulting pixels — the pixels were measured in a real browser at
 * 390 and 768. The pair is the point: a size assertion in jsdom would pass
 * against nothing at all.
 */

describe("the hit area", () => {
  it("is on every button, whatever it looks like", () => {
    render(<Button size="sm">Small</Button>);

    const button = document.querySelector("button");

    // The visual box stays small — growing every button to 44px would wreck
    // the density of the desktop design, which the same rule set protects.
    expect(button?.className).toContain("h-7");
    expect(button?.className).toContain("after:size-11");
  });

  it("makes an icon button a real 44px box rather than a pseudo-element", () => {
    render(<Button size="icon" aria-label="Notifications" />);

    /*
     * Icon buttons sit in the chrome at 8px gaps. An invisible hit area there
     * would overlap its neighbour's and break the other half of R-05 — the
     * 8px separation — so these grow for real.
     */
    expect(document.querySelector("button")?.className).toContain("size-11");
  });

  it("is available to controls that are not Buttons", () => {
    // A link acting as a primary action, a bare <button> in a table header.
    expect(TOUCH_TARGET).toContain("after:size-11");
    expect(TOUCH_TARGET).toContain("relative");

    // Behind the content, so it can never cover a neighbour's text.
    expect(TOUCH_TARGET).toContain("-z-10");
  });
});
