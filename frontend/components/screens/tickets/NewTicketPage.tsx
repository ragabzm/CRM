"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";

import { NewTicketScreen } from "./NewTicketScreen";

interface Named {
  id: number;
  name: string;
}

/**
 * Supplies the new-ticket form with its reference data and the router.
 *
 * The form itself was written, tested and marked done — and never mounted on a
 * route, so for two stories an agent could not open a ticket from the
 * application at all. Every ticket arrived from the portal or from email. The
 * plan that built it lost its "mount the form at
 * `app/(app)/tickets/new/page.tsx`" line in a rewrite, and the component was
 * built exactly as asked.
 *
 * Shaped after `TicketDetailPage` deliberately, including the failure
 * behaviour — with one difference: a select that will not load is reported
 * here rather than swallowed. On the detail screen an empty select still
 * leaves an agent a readable ticket; here it leaves them a form they cannot
 * complete, and silence would look identical to "this business has no
 * departments".
 */
export function NewTicketPage() {
  const router = useRouter();
  const search = useSearchParams();

  const [categories, setCategories] = useState<Named[]>([]);
  const [departments, setDepartments] = useState<Named[]>([]);
  const [referenceFailed, setReferenceFailed] = useState(false);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      const { request } = await import("@/lib/api/request");

      const load = async (path: string, set: (items: Named[]) => void): Promise<void> => {
        try {
          const body = await request<{ data: Named[] }>(path, { method: "GET" });

          if (!cancelled) set(body.data);
        } catch {
          if (!cancelled) setReferenceFailed(true);
        }
      };

      await Promise.all([
        load("/ticket-categories", setCategories),
        load("/departments", setDepartments),
      ]);
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <NewTicketScreen
      categories={categories}
      departments={departments}
      referenceFailed={referenceFailed}
      /*
       * Opened from a customer's profile, the customer is already known. The
       * id travels in the URL so the link is shareable and a reload does not
       * lose it.
       */
      {...(search.get("customer") === null ? {} : { customerId: String(search.get("customer")) })}
      onCreated={(ticketId) => router.push(`/tickets/${ticketId}`)}
      // Back to the queue, not `history.back()`: somebody who arrived here
      // from a link has no history to go back to.
      onCancel={() => router.push("/tickets")}
    />
  );
}
