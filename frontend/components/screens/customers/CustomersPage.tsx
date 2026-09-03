"use client";

import { useRouter } from "next/navigation";

import { CustomersScreen } from "./CustomersScreen";
import { useDepartments } from "./useDepartments";

export function CustomersPage() {
  const router = useRouter();
  const departments = useDepartments();

  return (
    <>
      <CustomersScreen
        departments={departments}
        onOpenCustomer={(id) => router.push(`/customers/${id}`)}
      />
    </>
  );
}
