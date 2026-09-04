import { NewTicketPage } from "@/components/screens/tickets/NewTicketPage";

/**
 * Where an agent starts a ticket.
 *
 * A static segment, so it wins over `[id]`. Before this route existed,
 * `/tickets/new` fell through to the dynamic route, which asked the API for a
 * ticket called "new", got a 404, and told the agent:
 *
 *   "This ticket no longer exists — it may have been merged or removed."
 *
 * A sentence about something that had never existed, on the way to a screen
 * that was never mounted.
 */
export default function Page() {
  return <NewTicketPage />;
}
