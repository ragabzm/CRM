"use client";

import { usePathname, useRouter } from "next/navigation";
import { useEffect } from "react";

import { SESSION_EXPIRED_EVENT } from "@/lib/api/client";

/** Where an expired session lands, carrying where it came from. */
export const SIGN_IN_PATH = "/sign-in";

/**
 * Sends a lapsed session back to sign-in, remembering where the reader was.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO: touch localStorage.
 *
 * Story 4.4's composer keeps unsent reply text under `composer-draft:<ticketId>`.
 * A session can expire while someone is mid-sentence, and clearing storage on
 * the way out would destroy work the reader never chose to discard — the session
 * ending is the product's problem, not theirs. So nothing here clears, and the
 * redirect carries `?redirect=` so they return to the same place with the draft
 * intact.
 *
 * There is nothing to clear for security reasons either: cookie-mode Sanctum
 * puts no credential in web storage in the first place.
 */
export function SessionExpiryListener() {
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    function onExpired() {
      const from = pathname ?? "/";

      // Already on the way out; a second redirect would lose the original
      // destination.
      if (from.startsWith(SIGN_IN_PATH)) return;

      router.replace(`${SIGN_IN_PATH}?redirect=${encodeURIComponent(from)}`);
    }

    window.addEventListener(SESSION_EXPIRED_EVENT, onExpired);

    return () => window.removeEventListener(SESSION_EXPIRED_EVENT, onExpired);
  }, [router, pathname]);

  return null;
}
