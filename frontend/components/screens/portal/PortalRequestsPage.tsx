"use client";

import { useRouter } from "next/navigation";

import { PortalGate } from "./PortalGate";
import { PortalRequestList } from "./PortalRequestList";

/**
 * Where a customer lands after signing in.
 *
 * Story 6.1 left this as an empty state so the shell's first destination was a
 * real page rather than a 404. It now lists what they have actually asked.
 */
export function PortalRequestsPage() {
  const router = useRouter();

  return (
    <PortalGate>
      {() => <PortalRequestList onOpen={(id) => router.push(`/portal/requests/${id}`)} />}
    </PortalGate>
  );
}
