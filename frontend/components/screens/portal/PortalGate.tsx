"use client";

import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";

import { PortalShell } from "@/components/shell/portal/PortalShell";
import { portalMe, type PortalAccount } from "@/lib/portal/api";

/**
 * Keeps the signed-out out of the signed-in pages.
 *
 * Client-side rather than through `middleware.ts` and `requireSession()`: those
 * ask the STAFF API who is signed in, and a customer is nobody as far as that
 * guard is concerned. Routing the portal through them would bounce every
 * customer to the agents' sign-in form.
 *
 * The real boundary is the API, which scopes everything to the account's own
 * customer and refuses an unauthenticated caller. This only decides which
 * screen to draw.
 */
export function PortalGate({
  children,
}: {
  children: (account: PortalAccount) => React.ReactNode;
}) {
  const router = useRouter();

  const [account, setAccount] = useState<PortalAccount | null>(null);
  const [checked, setChecked] = useState(false);

  const check = useCallback(() => {
    portalMe()
      .then(setAccount)
      .catch(() => router.replace("/portal/sign-in"))
      .finally(() => setChecked(true));
  }, [router]);

  useEffect(() => {
    // Deferred: setting state straight from an effect body cascades renders.
    void Promise.resolve().then(check);
  }, [check]);

  if (!checked || account === null) {
    // The shell without its navigation: three links that will bounce you are
    // not navigation.
    return <PortalShell signedIn={false}>{null}</PortalShell>;
  }

  return <PortalShell>{children(account)}</PortalShell>;
}
