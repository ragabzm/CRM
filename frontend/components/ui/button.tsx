import { cva, type VariantProps } from "class-variance-authority";
import { TriangleAlert } from "lucide-react";
import { Slot } from "radix-ui";
import * as React from "react";

import { cn } from "@/lib/utils";

/**
 * Layer-A button, restyled to the semantic tokens.
 *
 * UX-03 / NFR-13: every variant is distinguishable **without colour**. Hue is
 * never the only signal, so each variant also differs in weight, border or
 * glyph:
 *
 * primary solid fill  + semibold the only filled accent button
 * secondary hairline border + medium outlined, not filled
 * ghost no border, no fill + medium chrome-free
 * destructive  solid fill + semibold + glyph carries a warning mark
 *
 * `data-variant` is rendered so tests (and a designer with a greyscale filter)
 * can assert the distinction survives desaturation.
 */
const buttonVariants = cva(
  [
    "group/button inline-flex shrink-0 select-none items-center justify-center gap-2",
    "whitespace-nowrap rounded-md border border-transparent bg-clip-padding",
    "transition-all",
    "disabled:pointer-events-none disabled:opacity-50",
    "[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    /*
     * The invisible 44x44. Centred on the control and behind it, so it never
     * covers a neighbour's text and never changes what anything looks like.
     */
    "relative after:absolute after:start-1/2 after:top-1/2 after:-z-10 after:size-11",
    "after:-translate-x-1/2 after:-translate-y-1/2 after:content-[''] rtl:after:translate-x-1/2",
  ].join(" "),
  {
    variants: {
      variant: {
        primary:
          "bg-accent-default text-accent-fg font-semibold hover:bg-accent-hover active:bg-accent-active",
        secondary:
          "border-border-strong bg-surface-raised text-fg-default font-medium hover:bg-surface-hover active:bg-surface-active",
        ghost:
          "bg-transparent text-fg-default font-medium hover:bg-surface-hover active:bg-surface-active",
        destructive:
          "bg-state-danger text-fg-inverse font-semibold hover:opacity-90 active:opacity-95",
      },
      /*
       * Every size carries a 44x44 HIT AREA regardless of how big it looks.
       *
       * R-05 of the responsive design: "44x44 CSS px minimum, applied at every
       * band, because width does not predict input" — a 1440px viewport may be
       * a touchscreen, and a phone may be driven by a Bluetooth keyboard. So
       * the target is not a mobile feature that switches on below 768px.
       *
       * The VISUAL box does not grow. Making every button 44px tall would
       * wreck the density of the desktop design, which R-02's second half
       * protects just as firmly as its first half protects mobile. Instead an
       * invisible `::after` is centred on the control and stretched to 44x44 —
       * the mockup's own `.hitwrap` pattern.
       *
       * `icon` is the exception and becomes a real 44px box: icon buttons sit
       * in the chrome at gap-2 (8px), and a pseudo-element hit area there
       * would overlap its neighbour's — breaking the other half of R-05, the
       * 8px separation.
       */
      size: {
        sm: "h-7 px-2.5 text-xs",
        md: "h-8 px-3 text-sm",
        lg: "h-9 px-4 text-md",
        icon: "size-11 p-0",
      },
    },
    defaultVariants: {
      variant: "secondary",
      size: "md",
    },
  },
);

export type ButtonVariant = NonNullable<VariantProps<typeof buttonVariants>["variant"]>;

type ButtonProps = React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean;
    /**
     * Leading glyph. `destructive` supplies a warning mark when none is given,
     * because a destructive action must not rely on red alone to read as
     * destructive.
     */
    icon?: React.ReactNode;
  };

function Button({
  className,
  variant = "secondary",
  size = "md",
  asChild = false,
  icon,
  children,
  ...props
}: ButtonProps) {
  const Comp = asChild ? Slot.Root : "button";

  // The glyph is the non-colour half of the destructive signal. Callers may
  // override it, but they cannot silently end up with colour as the only cue.
  const resolvedIcon =
    icon ?? (variant === "destructive" ? <TriangleAlert aria-hidden="true" /> : null);

  return (
    <Comp
      data-slot="button"
      data-variant={variant}
      data-size={size}
      className={cn(buttonVariants({ variant, size }), className)}
      {...props}
    >
      {asChild ? (
        children
      ) : (
        <>
          {resolvedIcon}
          {children}
        </>
      )}
    </Comp>
  );
}

export { Button, buttonVariants };
