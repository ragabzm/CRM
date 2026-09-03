"use client";

import { useRouter } from "next/navigation";

import { PortalGate } from "./PortalGate";
import { PortalNewRequest } from "./PortalNewRequest";

/** Asking us something, behind the portal gate. */
export function PortalNewRequestPage() {
  const router = useRouter();

  return (
    <PortalGate>
      {(account) =>
        account.customer_id === null ? null : (
          <PortalNewRequest
            customerId={account.customer_id}
            // Straight to the request they just made: the confirmation somebody
            // wants is seeing the thing exist, not a message saying it does.
            onSubmitted={(id) => router.push(`/portal/requests/${id}`)}
          />
        )
      }
    </PortalGate>
  );
}
