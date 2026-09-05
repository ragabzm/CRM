"use client";

import { request } from "@/lib/api/request";

/** What a mapping is keyed on. The ORDER is the precedence: category wins. */
export type MappingSource = "category" | "department";

/** Where it sends a ticket. */
export type MappingTarget = "agent" | "department";

export interface AssignmentMapping {
  id: number;
  source_type: MappingSource;
  source_id: number;
  target_type: MappingTarget;
  target_id: number;
  /**
   * Whether this row can actually fire.
   *
   * A mapping pointing at somebody who left is worse than no mapping: the
   * queue looks like it is being sorted while every matching ticket quietly
   * stays unassigned.
   */
  active: boolean;
  inactive_reason: string | null;
}

export interface AssignmentMappingList {
  data: AssignmentMapping[];
  /** Highest precedence first, from the server, so the UI does not restate it. */
  precedence: MappingSource[];
}

export function listAssignmentMappings(
  fetchImpl: typeof fetch = fetch,
): Promise<AssignmentMappingList> {
  return request<AssignmentMappingList>("/admin/assignment-mappings", { fetchImpl });
}

export function createAssignmentMapping(
  input: {
    source_type: MappingSource;
    source_id: number;
    target_type: MappingTarget;
    target_id: number;
  },
  fetchImpl: typeof fetch = fetch,
): Promise<AssignmentMapping> {
  return request<AssignmentMapping>("/admin/assignment-mappings", {
    method: "POST",
    body: JSON.stringify(input),
    fetchImpl,
  });
}

export function deleteAssignmentMapping(
  id: number,
  fetchImpl: typeof fetch = fetch,
): Promise<{ deleted: number }> {
  return request(`/admin/assignment-mappings/${id}`, { method: "DELETE", fetchImpl });
}
