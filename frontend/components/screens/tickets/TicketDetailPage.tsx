"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { useCurrentUser } from "@/lib/auth/useCurrentUser";

import { TicketDetailScreen } from "./TicketDetailScreen";

interface Named {
  id: number;
  name: string;
}

/**
 * Supplies the ticket workspace with the lists its selects need, and the router.
 *
 * Separate from the screen so the screen can be rendered in a test without a
 * router, and so the reference data is fetched once here rather than by each
 * select that happens to need it.
 */
export function TicketDetailPage({ ticketId }: { ticketId: string }) {
  const router = useRouter();
  const user = useCurrentUser();

  const [categories, setCategories] = useState<Named[]>([]);
  const [assignees, setAssignees] = useState<Named[]>([]);
  const [departments, setDepartments] = useState<Named[]>([]);

  useEffect(() => {
    /*
     * Reference data only. Each failure is swallowed on its own: a select with
     * no options is a smaller problem than a workspace that will not open, and
     * an agent can still read the ticket and reply.
     */
    void loadInto("/ticket-categories", setCategories);
    void loadInto("/assignees", setAssignees);
    void loadInto("/departments", setDepartments);
  }, []);

  return (
    <TicketDetailScreen
      ticketId={ticketId}
      categories={categories}
      assignees={assignees}
      departments={departments}
      // Reading a ticket and changing it are different permissions. An agent
      // who holds only the first gets a rail they can read.
      editable={user?.roles?.some((role) => role !== "customer") ?? false}
      onNavigate={(href) => router.push(href)}
    />
  );
}

async function loadInto(path: string, set: (items: Named[]) => void): Promise<void> {
  try {
    const { request } = await import("@/lib/api/request");
    const body = await request<{ data: Named[] }>(path, { method: "GET" });

    set(body.data);
  } catch {
    set([]);
  }
}
