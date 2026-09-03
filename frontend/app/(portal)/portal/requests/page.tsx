import { PortalRequestsPage } from "@/components/screens/portal/PortalRequestsPage";

/**
 * "My requests" — the destination a customer lands on after signing in.
 *
 * The LIST itself is Story 6.2. This story owns the identity and the shell, so
 * the page exists, is gated, and says plainly that there is nothing here yet
 * rather than 404-ing the link the shell points at.
 */
export default function Page() {
  return <PortalRequestsPage />;
}
