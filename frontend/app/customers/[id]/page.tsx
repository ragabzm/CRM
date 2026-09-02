import { CustomerProfilePage } from "@/components/screens/customers/CustomerProfilePage";

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return <CustomerProfilePage customerId={id} />;
}
