"use client";

import { fetchBranding, NO_BRANDING, type BrandingValues } from "@/lib/api/branding";
import { useEffect, useState } from "react";

export interface BrandHeaderProps {
  /** Shown when no logo is configured. The product's own name. */
  fallback: string;
}

/**
 * The logo and the header line, at the top of a customer surface.
 *
 * The logo is served from the OBJECT STORE, not from this origin: it goes
 * through the Story 3.2 attachment path — validated on upload, scanned, and
 * handed out as a storage URL. An image an administrator uploaded and the
 * application then served from its own origin is a file somebody else's
 * browser executes in our security context.
 *
 * When there is no logo, the product's own name stands in. Not an empty box
 * and not a placeholder graphic — a customer who arrives at an unbranded
 * portal should see a finished page, not a gap where a logo goes.
 */
export function BrandHeader({ fallback }: BrandHeaderProps) {
  const [branding, setBranding] = useState<BrandingValues>(NO_BRANDING);

  useEffect(() => {
    let cancelled = false;

    void fetchBranding()
      .then((values) => {
        if (!cancelled) setBranding(values);
      })
      .catch(() => {});

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <span className="flex items-center gap-2" data-slot="brand-header">
      {branding.logo_url === null ? (
        <span className="font-semibold text-fg-default">{fallback}</span>
      ) : (
        /*
         * `alt` is the organisation's header line, or the product name.
         * A logo with an empty alt is a logo a screen reader announces as
         * nothing, which on the portal's only masthead is the whole heading.
         */
        <img
          src={branding.logo_url}
          alt={branding.header ?? fallback}
          className="h-6 w-auto"
          data-slot="brand-logo"
        />
      )}

      {branding.header !== null && (
        <span className="text-sm text-fg-muted" dir="auto" data-slot="brand-line">
          {branding.header}
        </span>
      )}
    </span>
  );
}
