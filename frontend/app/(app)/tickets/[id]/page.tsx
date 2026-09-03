import { TicketDetailPage } from "@/components/screens/tickets/TicketDetailPage";

/**
 * One ticket's workspace.
 *
 * Inside `(app)`, so the session gate and the chrome both come from the group
 * layout — and so the sidebar's Tickets link finally leads somewhere. It has
 * pointed at this route since the shell was built; until now it produced a
 * 404.
 */
export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return <TicketDetailPage ticketId={id} />;
}
