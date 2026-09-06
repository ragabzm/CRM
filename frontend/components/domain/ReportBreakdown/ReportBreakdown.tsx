"use client";

import { useTranslations } from "next-intl";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import type { ReportFigure } from "@/lib/api/reports";
import { useFormat } from "@/lib/format/useFormat";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface ReportBreakdownProps {
  caption: string;
  rows: ReportFigure[];
  /** Rendered when a row has no name of its own — uncategorised, unassigned. */
  noneLabel: string;
  onOpen: (filters: Record<string, string | number>) => void;
}

/**
 * One breakdown table: a label, a count, and a way in.
 *
 * A row with a NULL label is not a gap. "Uncategorised" and "Unassigned" are
 * real answers and often the interesting ones — a desk where a third of the
 * month has no category has a problem this row is the only place to see, and
 * rendering it as an empty cell would hide exactly that.
 */
export function ReportBreakdown({ caption, rows, noneLabel, onOpen }: ReportBreakdownProps) {
  const t = useTranslations("reports");
  const format = useFormat();

  if (rows.length === 0) {
    return (
      <section className="flex flex-col gap-2">
        <h3 className="text-sm font-semibold text-fg-default">{caption}</h3>
        <EmptyState headline={t("empty")} description={t("emptyBody")} />
      </section>
    );
  }

  return (
    <section className="flex flex-col gap-2" data-slot="report-breakdown">
      <h3 className="text-sm font-semibold text-fg-default">{caption}</h3>

      <table className="w-full text-sm">
        <caption className="sr-only">{caption}</caption>

        <tbody className="divide-y divide-border-subtle">
          {rows.map((row) => (
            <tr key={row.key} data-slot="breakdown-row">
              <td className="py-2">
                <button
                  type="button"
                  onClick={() => onOpen(row.filters)}
                  className={cn("text-start underline text-fg-default", TOUCH_TARGET)}
                  data-slot="breakdown-open"
                >
                  <bdi dir="auto">{row.label ?? noneLabel}</bdi>
                </button>
              </td>

              <td className="num py-2 text-end tabular-nums text-fg-default">
                {format.number(row.value)}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}
