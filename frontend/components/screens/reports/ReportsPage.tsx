"use client";

import { useRouter } from "next/navigation";

import { ticketListQuery, type TicketListParams } from "@/lib/api/tickets";

import { ReportsScreen } from "./ReportsScreen";

/**
 * Supplies the reports screen with the router.
 *
 * The click-through builds a Story 4.5 ticket-list URL from the filters the
 * SERVER sent with each figure — so the destination is the one ticket list,
 * not a second report-only list that drifts from it, and the link cannot
 * disagree with the number it came from.
 */
export function ReportsPage() {
  const router = useRouter();

  return (
    <ReportsScreen
      onOpen={(filters) => router.push(`/tickets?${ticketListQuery(filters as TicketListParams)}`)}
    />
  );
}
