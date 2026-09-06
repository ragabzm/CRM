"use client";

import { useEffect, useState, type ReactNode } from "react";

import { fetchBranding, NO_BRANDING, type BrandingValues } from "@/lib/api/branding";

export interface BrandedSurfaceProps {
  children: ReactNode;
}

/**
 * Puts the brand on a layout root, and only on the four that get one.
 *
 * The customer portal, the public web form, the live-chat widget and outbound
 * email. It reaches NO STAFF SURFACE — and that is structural rather than a
 * review item: the staff layout root never mounts this, so there is nothing
 * there for `--brand-primary` to resolve against and a token referencing it
 * falls back to the design system on its own.
 *
 * The values arrive as CSS CUSTOM PROPERTIES rather than as props threaded
 * through a component tree. Two reasons, and the second is the one that
 * matters: a property set on the root reaches every descendant without any of
 * them knowing branding exists, and — because it is only presentation — there
 * is no path by which a component could branch on it and render a different
 * control.
 *
 * A failure to load is silent and complete. The surface renders in the default
 * design system, which is a finished product rather than a broken one; a
 * customer waiting on a spinner because a logo could not be fetched would be a
 * worse outcome than a page with no logo.
 */
export function BrandedSurface({ children }: BrandedSurfaceProps) {
  const [branding, setBranding] = useState<BrandingValues>(NO_BRANDING);

  useEffect(() => {
    let cancelled = false;

    void fetchBranding()
      .then((values) => {
        if (!cancelled) setBranding(values);
      })
      .catch(() => {
        /*
         * Swallowed on purpose. Branding is decoration on a surface that has
         * to work without it, and an error here would be an error about a
         * logo shown to somebody trying to report a billing problem.
         */
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div
      data-slot="branded-surface"
      data-branded={branding.primary_colour !== null}
      /*
       * Only the properties that have a value. Setting `--brand-primary` to
       * an empty string would make every `var(--brand-primary, fallback)`
       * below resolve to nothing rather than to its fallback — a brand that
       * was never configured would blank the surface instead of leaving it
       * alone.
       */
      style={
        branding.primary_colour === null
          ? undefined
          : ({ "--brand-primary": branding.primary_colour } as React.CSSProperties)
      }
    >
      {children}
    </div>
  );
}
