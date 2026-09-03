"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useCallback, useMemo } from "react";

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
export function TicketListPage() {
  const router = useRouter();
  const search = useSearchParams();

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

    const q = search.get("q");
    if (q !== null && q !== "") built.q = q;

    const sort = search.get("sort");
    if (sort !== null) built.sort = sort;

    const direction = search.get("direction");
    if (direction === "asc" || direction === "desc") built.direction = direction;

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
    />
  );
}
