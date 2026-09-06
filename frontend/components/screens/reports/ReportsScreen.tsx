"use client";

import { useTranslations } from "next-intl";
import { useCallback, useState } from "react";

import { ComplianceMeter } from "@/components/domain/ComplianceMeter/ComplianceMeter";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { ReportBreakdown } from "@/components/domain/ReportBreakdown/ReportBreakdown";
import { ReportCards } from "@/components/domain/ReportCards/ReportCards";
import { ReportRange } from "@/components/domain/ReportRange/ReportRange";
import { RowSkeleton } from "@/components/domain/RowSkeleton/RowSkeleton";
import { fetchReports } from "@/lib/api/reports";
import { useFreshQuery } from "@/lib/data/useFreshQuery";
import { useFormat } from "@/lib/format/useFormat";

export interface ReportsScreenProps {
  /** Opens the ticket list with a figure's own filters. */
  onOpen: (filters: Record<string, string | number>) => void;
  /** Defaults to the current month. Injected so the tests are not time-bound. */
  initialPeriod?: { from: string; to: string };
}

/** The current calendar month, which is the question people ask by default. */
function thisMonth(): { from: string; to: string } {
  const now = new Date();
  const first = new Date(now.getFullYear(), now.getMonth(), 1);
  const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  const iso = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

  return { from: iso(first), to: iso(last) };
}

/**
 * A small set of figures a supervisor can trust and click into.
 *
 * NOT a dashboard. Every number here resolves to the tickets behind it in one
 * action, because a figure nobody can audit is a figure nobody believes — and
 * a figure with no click-through does not ship.
 *
 * It also deliberately does NOT duplicate Home's live counts. Those are "how
 * much is waiting right now"; these are "what happened over a period I chose".
 * The two are the same shape of figure answering different questions, and
 * mixing them on one surface is how somebody reads a live number as a monthly
 * one.
 *
 * There is no export. No CSV, no PDF, no print view, no download control and
 * no share link anywhere below.
 */
export function ReportsScreen({ onOpen, initialPeriod }: ReportsScreenProps) {
  const t = useTranslations("reports");
  const format = useFormat();

  const [period, setPeriod] = useState(initialPeriod ?? thisMonth);

  const fetcher = useCallback(() => fetchReports(period), [period.from, period.to]); // eslint-disable-line react-hooks/exhaustive-deps

  const reports = useFreshQuery(`reports:${period.from}:${period.to}`, fetcher, {
    // No interval. A report is a question somebody asked about a fixed period;
    // an answer that changed under them while they read it would be worse.
    refetchOnWindowFocus: false,
  });

  if (reports.status === 403) {
    /*
     * UX-07: a refusal states what was refused and who to ask. It never
     * renders as empty data — a report set showing zeros because somebody
     * lacks a capability is the worst possible failure on this surface: it
     * looks like an answer, and the answer is "your desk did nothing".
     */
    return (
      <ForbiddenState
        headline={t("forbidden")}
        description={t("forbiddenBody")}
        withheldLabel={t("title")}
      />
    );
  }

  const data = reports.data;
  const empty = data !== null && data.volume.cards.every((card) => card.value === 0);

  return (
    <div className="flex flex-col gap-6" data-slot="reports">
      <div className="flex flex-col gap-0.5">
        <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>
        <p className="text-xs text-fg-muted">{t("subtitle")}</p>
      </div>

      <ReportRange from={period.from} to={period.to} onChange={setPeriod} />

      {reports.stale && data === null && (
        <FormAlert tone="error" action={{ label: t("retry"), onSelect: reports.refetch }}>
          {t("loadError")}
        </FormAlert>
      )}

      {reports.loading && data === null ? (
        <RowSkeleton label={t("loading")} rows={4} />
      ) : data === null ? null : empty ? (
        /*
         * An unmistakable empty state, not a grid of zeros. Zeros presented as
         * data look like an answer, and somebody will quote them.
         */
        <EmptyState
          headline={t("empty")}
          description={t("emptyBody", {
            from: format.date(new Date(data.period.from)),
            to: format.date(new Date(data.period.to)),
          })}
        />
      ) : (
        <>
          <ReportCards cards={data.volume.cards} onOpen={onOpen} />

          <section className="flex flex-col gap-3">
            <h2 className="text-base font-semibold text-fg-default">{t("sla.title")}</h2>

            <div className="grid gap-3 tablet:grid-cols-2">
              <ComplianceMeter target="response" performance={data.sla.response} onOpen={onOpen} />
              <ComplianceMeter
                target="resolution"
                performance={data.sla.resolution}
                onOpen={onOpen}
              />
            </div>
          </section>

          <section className="flex flex-col gap-3" data-slot="satisfaction-report">
            <h2 className="text-base font-semibold text-fg-default">{t("satisfaction.title")}</h2>

            {data.satisfaction.answered === 0 ? (
              // Nobody answered. Not nought per cent — that would say every
              // customer who replied was unhappy.
              <p className="text-sm text-fg-muted">{t("satisfaction.nobodyAnswered")}</p>
            ) : (
              <div className="flex flex-wrap items-baseline gap-x-6 gap-y-2">
                <p className="num text-2xl font-semibold tabular-nums text-fg-default">
                  {format.number(data.satisfaction.positive_rate ?? 0, {
                    style: "percent",
                    maximumFractionDigits: 1,
                  })}
                </p>

                {/*
                  The volume beside the rate, never behind it. A rate over four
                  answers and a rate over four hundred are not the same claim.
                */}
                <button
                  type="button"
                  onClick={() => onOpen(data.satisfaction.filters)}
                  className="text-sm underline text-fg-default"
                  data-slot="satisfaction-answered"
                >
                  {t("satisfaction.answered", {
                    count: format.number(data.satisfaction.answered),
                  })}
                </button>

                <button
                  type="button"
                  onClick={() => onOpen(data.satisfaction.positive_filters)}
                  className="text-sm underline text-fg-muted"
                  data-slot="satisfaction-positive"
                >
                  {t("satisfaction.positive", {
                    count: format.number(data.satisfaction.positive),
                  })}
                </button>

                <button
                  type="button"
                  onClick={() => onOpen(data.satisfaction.negative_filters)}
                  className="text-sm underline text-fg-muted"
                  data-slot="satisfaction-negative"
                >
                  {t("satisfaction.negative", {
                    count: format.number(data.satisfaction.negative),
                  })}
                </button>
              </div>
            )}
          </section>

          <div className="grid gap-6 tablet:grid-cols-3">
            <ReportBreakdown
              caption={t("breakdown.status")}
              rows={data.volume.by_status}
              noneLabel={t("breakdown.none")}
              onOpen={onOpen}
            />
            <ReportBreakdown
              caption={t("breakdown.category")}
              rows={data.volume.by_category}
              noneLabel={t("breakdown.uncategorised")}
              onOpen={onOpen}
            />
            <ReportBreakdown
              caption={t("breakdown.assignee")}
              rows={data.volume.by_assignee}
              noneLabel={t("breakdown.unassigned")}
              onOpen={onOpen}
            />
          </div>
        </>
      )}
    </div>
  );
}
