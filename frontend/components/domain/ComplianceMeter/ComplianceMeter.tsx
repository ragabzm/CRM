"use client";

import { useTranslations } from "next-intl";

import type { SlaTargetPerformance } from "@/lib/api/reports";
import { useFormat } from "@/lib/format/useFormat";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface ComplianceMeterProps {
  /** `response` or `resolution` — the two clocks, and there is no third. */
  target: "response" | "resolution";
  performance: SlaTargetPerformance;
  onOpen: (filters: Record<string, string | number>) => void;
}

/**
 * Did we hit this target, how often did we miss, and how long did it take.
 *
 * NOTHING TO MEASURE IS NOT PERFECT. A period with no tickets renders as "not
 * measured", never as 100% — a desk congratulating itself on a month it did
 * not work is the single most misleading thing this surface could show.
 *
 * The meter is a bar AND a number AND a word. A bar alone is unreadable in
 * greyscale and says nothing to a screen reader; the figure beside it is what
 * anybody actually quotes.
 */
export function ComplianceMeter({ target, performance, onOpen }: ComplianceMeterProps) {
  const t = useTranslations("reports.sla");
  const format = useFormat();

  const rate = performance.compliance_rate;
  const measured = rate !== null;

  return (
    <section
      className="flex flex-col gap-2 rounded-md border border-border-default p-3"
      data-slot="compliance-meter"
      data-target={target}
      data-measured={measured}
    >
      <h3 className="text-sm font-semibold text-fg-default">{t(target)}</h3>

      {measured ? (
        <>
          <p className="num text-2xl font-semibold tabular-nums text-fg-default">
            {format.number(rate, { style: "percent", maximumFractionDigits: 1 })}
          </p>

          {/*
            `progressbar` with its value spelled out, so the bar is not the
            only way the figure exists. A tinted rectangle is invisible to a
            screen reader and to anybody reading a printed report.
          */}
          <div
            role="progressbar"
            aria-valuenow={Math.round(rate * 100)}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label={t(target)}
            className="h-1.5 w-full overflow-hidden rounded-full bg-surface-sunken"
          >
            <div
              className="h-full bg-fg-default"
              style={{ inlineSize: `${Math.round(rate * 100)}%` }}
            />
          </div>
        </>
      ) : (
        /*
         * Said in words. A grid of zeros presented as data is worse than an
         * empty state, because it looks like an answer.
         */
        <p className="text-sm text-fg-muted" data-slot="not-measured">
          {t("notMeasured")}
        </p>
      )}

      <dl className="flex flex-wrap gap-x-6 gap-y-1 text-xs text-fg-muted">
        <div className="flex gap-1">
          <dt>{t("breaches")}</dt>
          <dd>
            <button
              type="button"
              onClick={() => onOpen(performance.filters)}
              className={cn("num underline tabular-nums text-fg-default", TOUCH_TARGET)}
              data-slot="breaches-open"
            >
              {format.number(performance.breaches)}
            </button>
          </dd>
        </div>

        <div className="flex gap-1">
          <dt>{t("average")}</dt>
          <dd className="num tabular-nums">
            {performance.average_minutes === null
              ? t("notMeasured")
              : t("minutes", { minutes: format.number(performance.average_minutes) })}
          </dd>
        </div>
      </dl>
    </section>
  );
}
