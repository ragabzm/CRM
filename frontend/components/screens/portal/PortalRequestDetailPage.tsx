"use client";

import { PortalGate } from "./PortalGate";
import { PortalRequestDetail } from "./PortalRequestDetail";

export function PortalRequestDetailPage({ requestId }: { requestId: string }) {
  return <PortalGate>{() => <PortalRequestDetail requestId={requestId} />}</PortalGate>;
}
