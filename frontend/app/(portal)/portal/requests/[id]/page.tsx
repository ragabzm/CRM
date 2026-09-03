import { PortalRequestDetailPage } from "@/components/screens/portal/PortalRequestDetailPage";

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return <PortalRequestDetailPage requestId={id} />;
}
