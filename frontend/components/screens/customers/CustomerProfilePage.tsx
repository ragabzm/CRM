"use client";

import { useRouter } from "next/navigation";

import { AppShell } from "@/components/shell/AppShell";

import { CustomerProfileScreen } from "./CustomerProfileScreen";
import { useDepartments } from "./useDepartments";

export function CustomerProfilePage({ customerId }: { customerId: string }) {
  const router = useRouter();
  const departments = useDepartments();

  return (
    <AppShell>
      <CustomerProfileScreen
        customerId={customerId}
        departments={departments}
        onOpenCustomer={(id) => router.push(`/customers/${id}`)}
      />
    </AppShell>
  );
}
