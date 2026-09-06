"use client";

import { request } from "@/lib/api/request";

/**
 * The fixed report set: one call, one filter, three sections.
 *
 * One request rather than three, because the three sections are one question
 * asked about one range. Fetching them separately would let a supervisor read
 * a satisfaction rate from one moment beside a volume from another, with
 * nothing on screen saying they disagree.
 *
 * Every figure arrives with the FILTERS that produced it — not a description
 * of them, the filters themselves — so a click-through lands on exactly the
 * tickets that were counted and cannot drift from the number.
 */

/** The query parameters a figure's ticket-list link is built from. */
export type FigureFilters = Record<string, string | number>;

export interface ReportFigure {
  key: string;
  /** Null when the row is the absence of a value — uncategorised, unassigned. */
  label?: string | null;
  value: number;
  filters: FigureFilters;
}

export interface SlaTargetPerformance {
  breaches: number;
  /** Null when there was nothing to measure — never 100% for an empty month. */
  compliance_rate: number | null;
  /** Null when nothing was measured. Zero would read as "instant". */
  average_minutes: number | null;
  measured: number;
  filters: FigureFilters;
}

export interface ReportSet {
  period: { from: string; to: string };
  volume: {
    cards: ReportFigure[];
    by_status: ReportFigure[];
    by_category: ReportFigure[];
    by_assignee: ReportFigure[];
  };
  sla: {
    tickets: number;
    breaches: number;
    response: SlaTargetPerformance;
    resolution: SlaTargetPerformance;
  };
  satisfaction: {
    answered: number;
    positive: number;
    negative: number;
    /** Null when nobody answered — which is not the same as everybody unhappy. */
    positive_rate: number | null;
    filters: FigureFilters;
    positive_filters: FigureFilters;
    negative_filters: FigureFilters;
  };
}

export async function fetchReports(
  period: { from: string; to: string },
  fetchImpl: typeof fetch = fetch,
): Promise<ReportSet> {
  const query = new URLSearchParams({ from: period.from, to: period.to });

  const body = await request<{ data: ReportSet }>(`/reports?${query.toString()}`, {
    method: "GET",
    fetchImpl,
  });

  return body.data;
}
