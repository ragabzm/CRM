"use client";

import { useTranslations } from "next-intl";

import type { SlaBlock, SlaStateValue, SlaTimer } from "@/lib/api/tickets";
import { useFormat } from "@/lib/format/useFormat";
import { cn } from "@/lib/utils";

export interface SlaIndicatorProps {
  /** Null means the engine is not tracking — which is not the same as fine. */
  sla: SlaBlock | null;
  /** `compact` for a list cell; `full` for the ticket's own screen. */
  variant?: "compact" | "full";
}

/**
 * Where a ticket stands against its targets.
 *
 * Five treatments, and the two that are easy to get wrong are the ones that
 * matter:
 *
 *   `paused`  — the clock has STOPPED, because the ticket is waiting on the
 *               customer. Drawn as neither good nor bad, because it is neither:
 *               showing it as on track would put it in an agent's "act on this"
 *               list for work that is not theirs to do.
 *   `null`    — nobody is tracking. Rendered as a dash, never as "on track": a
 *               deployment with the engine off knows nothing about its targets,
 *               and a green badge would be a claim it cannot support.
 *
 * Every state carries a WORD as well as a colour. A red dot says nothing to a
 * screen reader and nothing in greyscale, and this is the badge an agent scans
 * a list by.
 */
export function SlaIndicator({ sla, variant = "compact" }: SlaIndicatorProps) {
  const t = useTranslations("tickets.sla");
  const format = useFormat();

  if (sla === null) {
    return (
      <span data-slot="sla-indicator" data-state="not-tracked" className="text-fg-muted">
        <span className="sr-only">{t("notTracked")}</span>
        <span aria-hidden="true">—</span>
      </span>
    );
  }

  if (variant === "compact") {
    return <Badge state={sla.state} timer={worseOf(sla)} t={t} format={format} />;
  }

  return (
    <dl data-slot="sla-indicator-full" className="flex flex-col gap-2 text-sm">
      <Row label={t("response")} timer={sla.response} t={t} format={format} />
      <Row label={t("resolution")} timer={sla.resolution} t={t} format={format} />
    </dl>
  );
}

/**
 * Colour AND weight AND a word.
 *
 * The paused treatment is deliberately muted rather than tinted: it is the one
 * state that asks nothing of the reader.
 */
const TREATMENT: Record<SlaStateValue, string> = {
  on_track: "text-fg-muted",
  at_risk: "font-semibold text-state-warning",
  breached: "font-semibold text-state-danger",
  met: "text-state-success",
  paused: "italic text-fg-muted",
};

function Badge({
  state,
  timer,
  t,
  format,
}: {
  state: SlaStateValue;
  timer: SlaTimer;
  t: ReturnType<typeof useTranslations<"tickets.sla">>;
  format: ReturnType<typeof useFormat>;
}) {
  return (
    <span
      data-slot="sla-indicator"
      data-state={state}
      title={state === "paused" ? t("pausedHint") : undefined}
      className={cn("inline-flex items-center gap-1.5 text-xs", TREATMENT[state])}
    >
      {/* The word, first. Colour is the secondary channel, not the message. */}
      <span>{t(`state.${state}`)}</span>

      {/* A countdown only where a clock is still running. "40 min left" on a
          finished ticket would be nonsense. */}
      {(state === "on_track" || state === "at_risk" || state === "breached") && (
        <span className="num" dir="ltr">
          {timer.remaining_minutes >= 0
            ? t("remaining", { minutes: format.number(timer.remaining_minutes) })
            : t("over", { minutes: format.number(Math.abs(timer.remaining_minutes)) })}
        </span>
      )}
    </span>
  );
}

function Row({
  label,
  timer,
  t,
  format,
}: {
  label: string;
  timer: SlaTimer;
  t: ReturnType<typeof useTranslations<"tickets.sla">>;
  format: ReturnType<typeof useFormat>;
}) {
  return (
    <div className="flex items-baseline justify-between gap-3">
      <dt className="text-fg-muted">{label}</dt>
      <dd>
        <Badge state={timer.state} timer={timer} t={t} format={format} />
      </dd>
    </div>
  );
}

/** The timer the headline badge is about — the worse of the two. */
function worseOf(sla: SlaBlock): SlaTimer {
  return sla.response.state === sla.state ? sla.response : sla.resolution;
}
