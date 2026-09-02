"use client";

import { useRouter } from "next/navigation";

import { AppShell } from "@/components/shell/AppShell";

import { CustomersScreen } from "./CustomersScreen";
import { useDepartments } from "./useDepartments";

export function CustomersPage() {
  const router = useRouter();
  const departments = useDepartments();

  return (
    <AppShell>
      <CustomersScreen
        departments={departments}
        onOpenCustomer={(id) => router.push(`/customers/${id}`)}
      />
    </AppShell>
  );
}
