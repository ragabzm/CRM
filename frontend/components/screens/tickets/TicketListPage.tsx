"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useMemo, useState } from "react";

import { ticketListQuery, type TicketListParams } from "@/lib/api/tickets";

import { TicketListScreen } from "./TicketListScreen";

/**
 * Reads the filters out of the URL and writes them back.
 *
 * The URL is the ONLY place list state lives. That is what makes a count tile
 * on Home a real link — the URL it points at IS the filter — and what lets an
 * agent send a colleague exactly what they are looking at. Nothing is kept in
 * localStorage, and there are no named views: a reload has to reproduce the
 * screen from the address bar alone or the address bar is lying.
 */
interface Named {
  id: number;
  name: string;
}

export function TicketListPage() {
  const router = useRouter();
  const search = useSearchParams();

  const [categories, setCategories] = useState<Named[]>([]);
  const [departments, setDepartments] = useState<Named[]>([]);
  const [assignees, setAssignees] = useState<Named[]>([]);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      const { request } = await import("@/lib/api/request");

      const load = async (path: string, set: (items: Named[]) => void): Promise<void> => {
        try {
          const body = await request<{ data: Named[] }>(path, { method: "GET" });

          if (!cancelled) set(body.data);
        } catch {
          /*
           * Swallowed HERE and nowhere else, because the consequence is
           * bounded: a filter that cannot load is hidden rather than shown
           * empty, and the list itself still works. Compare the new-ticket
           * form, where the same failure leaves a form nobody can submit and
           * is reported out loud.
           */
        }
      };

      await Promise.all([
        load("/ticket-categories", setCategories),
        load("/departments", setDepartments),
        load("/assignees", setAssignees),
      ]);
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  const params = useMemo((): TicketListParams => {
    const list = (key: string): string[] => {
      const raw = search.get(key);

      return raw === null || raw === "" ? [] : raw.split(",");
    };

    const assignees = list("assignee_id").map((value) =>
      value === "unassigned" ? ("unassigned" as const) : Number(value),
    );

    const built: TicketListParams = {};

    if (list("status").length > 0) built.status = list("status");
    if (list("priority").length > 0) built.priority = list("priority");
    if (assignees.length > 0) built.assignee_id = assignees;
    if (list("department_id").length > 0) {
      built.department_id = list("department_id").map(Number);
    }
    if (list("category_id").length > 0) built.category_id = list("category_id").map(Number);

    const slaState = search.get("sla_state");
    if (
      slaState === "on_track" ||
      slaState === "at_risk" ||
      slaState === "breached" ||
      slaState === "met" ||
      slaState === "paused"
    ) {
      built.sla_state = slaState;
    }

    /*
     * Read from the URL so the filter survives a reload and can be shared as
     * a link — which is the whole reason these live in the address bar. Both
     * spellings are accepted because both are things a person types.
     */
    const escalated = search.get("escalated");
    if (escalated !== null) {
      if (escalated === "1" || escalated === "true") built.escalated = true;
      else if (escalated === "0" || escalated === "false") built.escalated = false;
    }

    const q = search.get("q");
    if (q !== null && q !== "") built.q = q;

    const sort = search.get("sort");
    if (sort !== null) built.sort = sort;

    const direction = search.get("direction");
    if (direction === "asc" || direction === "desc") built.direction = direction;

    // The page belongs in the URL like every other piece of list state: a
    // reload has to reproduce the screen from the address bar alone.
    const page = Number(search.get("page"));
    if (Number.isInteger(page) && page > 1) built.page = page;

    return built;
  }, [search]);

  const apply = useCallback(
    (next: TicketListParams) => {
      const query = ticketListQuery(next);

      // `replace`, not `push`: adjusting a filter is refining one view, not
      // visiting a new place. Pushing would make Back walk through every
      // keystroke of a search.
      router.replace(query === "" ? "/tickets" : `/tickets?${query}`);
    },
    [router],
  );

  return (
    <TicketListScreen
      params={params}
      onParamsChange={apply}
      onOpen={(id) => router.push(`/tickets/${id}`)}
      categories={categories}
      departments={departments}
      assignees={assignees}
    />
  );
}
