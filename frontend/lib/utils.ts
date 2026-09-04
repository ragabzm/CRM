import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/**
 * A 44x44 hit area that does not change how anything looks.
 *
 * R-05 of the responsive design: "44x44 CSS px minimum with 8px separation,
 * applied at EVERY band, because width does not predict input". A 1440px
 * viewport may be a touchscreen and a phone may be driven by a Bluetooth
 * keyboard, so this is not a mobile feature that switches on below 768px.
 *
 * An invisible `::after` centred on the control rather than a bigger box:
 * growing every control to 44px would wreck the density of the desktop design,
 * which the same rule set protects just as firmly. `-z-10` keeps it behind the
 * content so it can never cover a neighbour's text.
 *
 * `Button` carries this already. Use it on the controls that are not Buttons —
 * a link acting as a primary action, a bare `<button>` in a table header.
 */
export const TOUCH_TARGET =
  "relative after:absolute after:start-1/2 after:top-1/2 after:-z-10 after:size-11 " +
  "after:-translate-x-1/2 after:-translate-y-1/2 after:content-[''] rtl:after:translate-x-1/2";
