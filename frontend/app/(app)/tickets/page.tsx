import { Suspense } from "react";

import { TicketListPage } from "@/components/screens/tickets/TicketListPage";

/**
 * The ticket list.
 *
 * This route is what the sidebar's Tickets link has pointed at since the shell
 * was built; until now it produced a 404.
 *
 * Wrapped in Suspense because the screen reads the URL with `useSearchParams`,
 * which opts its subtree into client-side rendering.
 */
export default function Page() {
  return (
    <Suspense>
      <TicketListPage />
    </Suspense>
  );
}
