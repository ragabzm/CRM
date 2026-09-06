"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface ReportRangeProps {
  from: string;
  to: string;
  onChange: (period: { from: string; to: string }) => void;
}

/**
 * The only filter on this surface.
 *
 * Two dates. There is deliberately no department, channel, priority, agent,
 * category or branch row beside them, and no saved range — every one of those
 * is a way of asking a question the product cannot promise a trustworthy
 * answer to, and a figure nobody trusts is worse than no figure.
 *
 * `type="date"` rather than a bespoke calendar: every mobile browser renders
 * its own picker, it is keyboard-reachable for free, and the value is an
 * ISO date in both locales — which is what keeps the range itself in Western
 * digits when everything around it is Arabic.
 */
export function ReportRange({ from, to, onChange }: ReportRangeProps) {
  const t = useTranslations("reports.range");

  const [start, setStart] = useState(from);
  const [end, setEnd] = useState(to);

  return (
    <form
      className="flex flex-wrap items-end gap-3"
      data-slot="report-range"
      onSubmit={(event) => {
        event.preventDefault();
        onChange({ from: start, to: end });
      }}
    >
      <label className="flex flex-col gap-1 text-sm">
        <span className="text-fg-muted">{t("from")}</span>
        <input
          type="date"
          value={start}
          max={end}
          onChange={(event) => setStart(event.target.value)}
          className={cn(
            "num rounded-md border border-border-default bg-surface-default px-2 py-1.5 text-sm",
            TOUCH_TARGET,
          )}
        />
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="text-fg-muted">{t("to")}</span>
        <input
          type="date"
          value={end}
          min={start}
          onChange={(event) => setEnd(event.target.value)}
          className={cn(
            "num rounded-md border border-border-default bg-surface-default px-2 py-1.5 text-sm",
            TOUCH_TARGET,
          )}
        />
      </label>

      <SubmitButton disabled={start === "" || end === ""}>{t("apply")}</SubmitButton>
    </form>
  );
}
