"use client";

import { useRouter } from "next/navigation";

import { CustomerProfileScreen } from "./CustomerProfileScreen";
import { useDepartments } from "./useDepartments";

export function CustomerProfilePage({ customerId }: { customerId: string }) {
  const router = useRouter();
  const departments = useDepartments();

  return (
    <>
      <CustomerProfileScreen
        customerId={customerId}
        departments={departments}
        onOpenCustomer={(id) => router.push(`/customers/${id}`)}
        onOpenTicket={(ticketId) => router.push(`/tickets/${ticketId}`)}
      />
    </>
  );
}
