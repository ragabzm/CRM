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
    const timer = worseOf(sla);

    return (
      <span data-slot="sla-cell" className="flex w-full flex-col items-end gap-1">
        <Badge state={sla.state} timer={timer} t={t} format={format} />

        {/*
          Not on a finished clock. A bar under "Met" would invite the reader to
          measure something that has stopped moving.
        */}
        {(sla.state === "on_track" || sla.state === "at_risk" || sla.state === "breached") && (
          <Meter state={sla.state} timer={timer} />
        )}
      </span>
    );
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
  /*
   * From `clock-*` — the semantic alias for the palette `tokens.css` labels
   * "The traffic light. Reserved for SLA."
   *
   * This used to read `text-state-warning` / `text-state-danger`: the generic
   * feedback colours, borrowed because the SLA palette had no alias and no
   * component could legally name it. So the traffic light stayed reserved for
   * something that never arrived, while the one component it was built for
   * used the colours meant for form errors.
   */
  on_track: "text-clock-on-track",
  at_risk: "font-semibold text-clock-at-risk",
  breached: "font-semibold text-clock-breached",
  met: "text-clock-met",
  paused: "italic text-clock-paused",
};

/** The filled part of the meter, per state. */
const BAR: Record<SlaStateValue, string> = {
  on_track: "bg-clock-on-track",
  at_risk: "bg-clock-at-risk",
  breached: "bg-clock-breached",
  met: "bg-clock-met",
  paused: "bg-clock-paused",
};

/**
 * How much of the target has gone, 0-100.
 *
 * Capped at 100 so a breached ticket draws a full bar rather than one that
 * overflows its own track — "how far past" is what the countdown says in
 * words, and a bar cannot express it anyway.
 */
function elapsedPercent(timer: SlaTimer): number {
  if (timer.target_minutes <= 0) return 0;

  return Math.min(
    100,
    Math.max(0, Math.round((timer.elapsed_minutes / timer.target_minutes) * 100)),
  );
}

/**
 * The progress bar the design has always specified.
 *
 * `--color-sla-track` sat in the token file from the first commit with the
 * comment saying what it was for, and nothing drew a bar. A time remaining
 * with no bar makes an agent do the arithmetic — "is 40 minutes of a 4 hour
 * target a lot?" — that the bar answers instantly.
 *
 * Decorative: the state and the countdown next to it already say everything
 * this shows, so announcing it again would be noise.
 */
function Meter({ state, timer }: { state: SlaStateValue; timer: SlaTimer }) {
  return (
    <span
      aria-hidden="true"
      data-slot="sla-meter"
      className="block h-1 w-full overflow-hidden rounded-full bg-clock-track"
    >
      <span
        className={cn("block h-full rounded-full", BAR[state])}
        style={{ width: `${elapsedPercent(timer)}%` }}
      />
    </span>
  );
}

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
        /*
         * `dir="ltr"` goes around the DURATION, never around the sentence.
         *
         * `tickets.sla.remaining` in Arabic is "فاضل {duration}" — an Arabic
         * phrase. Wrapping the whole of it in `dir="ltr"` reorders it, and the
         * result on screen was `4 hفاضل`: the unit welded to the word and the
         * number torn off its unit. The isolation belongs on the run of digits
         * and nothing else.
         */
        <span>
          {timer.remaining_minutes >= 0
            ? t.rich("remaining", {
                duration: () => (
                  <Duration minutes={timer.remaining_minutes} t={t} format={format} />
                ),
              })
            : t.rich("over", {
                duration: () => (
                  <Duration minutes={Math.abs(timer.remaining_minutes)} t={t} format={format} />
                ),
              })}
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
    <div className="flex flex-col gap-1">
      <div className="flex items-baseline justify-between gap-3">
        <dt className="text-fg-muted">{label}</dt>
        <dd>
          <Badge state={timer.state} timer={timer} t={t} format={format} />
        </dd>
      </div>

      {(timer.state === "on_track" || timer.state === "at_risk" || timer.state === "breached") && (
        <>
          <Meter state={timer.state} timer={timer} />

          {/*
            The two facts the API has sent since Story 5.3 and nothing showed:
            how much of the target has gone, and when it runs out. "At risk"
            without either is a colour with no argument behind it.
          */}
          <p className="flex flex-wrap gap-x-2 text-xs text-fg-muted">
            <span>
              {t.rich("elapsedPercent", {
                percent: () => (
                  <bdi className="num" dir="ltr">
                    {format.number(elapsedPercent(timer))}
                  </bdi>
                ),
              })}
            </span>
            {timer.due_at !== null && (
              <span>{t("dueAt", { at: format.dateTime(timer.due_at) })}</span>
            )}
          </p>
        </>
      )}
    </div>
  );
}

/**
 * The duration, with each number isolated and each unit translated.
 *
 * Two pieces of the same bug lived here. The units were Latin letters typed
 * into the source — `43d 4h` stayed Latin in an Arabic interface no matter
 * what the surrounding copy said — and the isolation was applied to the whole
 * phrase rather than to the digits, which reordered the Arabic around them.
 */
function Duration({
  minutes,
  t,
  format,
}: {
  minutes: number;
  t: ReturnType<typeof useTranslations<"tickets.sla">>;
  format: ReturnType<typeof useFormat>;
}) {
  const parts = partsOf(minutes);

  return (
    <>
      {parts.map((part, index) => (
        <span key={part.unit}>
          {index > 0 ? " " : ""}
          {/*
            `bdi` around the number alone. The unit beside it is a translated
            word and belongs to the surrounding paragraph's direction.
          */}
          <bdi className="num" dir="ltr">
            {format.number(part.value)}
          </bdi>
          {t(`units.${part.unit}`)}
        </span>
      ))}
    </>
  );
}

/**
 * A span of time somebody can read at a glance.
 *
 * This used to print raw minutes, so a ticket two weeks past its target read
 * "20,880 min over" — a number nobody can convert while scanning a queue, on
 * the badge that exists precisely to be scanned. Two units at most: the third
 * adds precision nobody is acting on.
 *
 * Working days and hours, not calendar ones. The engine counts in working
 * time, so dividing by 24 would promise a deadline the schedule does not.
 */
function partsOf(minutes: number): Array<{ unit: "days" | "hours" | "minutes"; value: number }> {
  const WORKING_DAY = 8 * 60;

  if (minutes >= WORKING_DAY) {
    const days = Math.floor(minutes / WORKING_DAY);
    const hours = Math.floor((minutes % WORKING_DAY) / 60);

    return hours === 0
      ? [{ unit: "days", value: days }]
      : [
          { unit: "days", value: days },
          { unit: "hours", value: hours },
        ];
  }

  if (minutes >= 60) {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0
      ? [{ unit: "hours", value: hours }]
      : [
          { unit: "hours", value: hours },
          { unit: "minutes", value: rest },
        ];
  }

  return [{ unit: "minutes", value: minutes }];
}

/** The timer the headline badge is about — the worse of the two. */
function worseOf(sla: SlaBlock): SlaTimer {
  return sla.response.state === sla.state ? sla.response : sla.resolution;
}
