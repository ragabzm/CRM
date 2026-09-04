import { useTranslations } from "next-intl";

import { cn } from "@/lib/utils";

export type UrgencyName = "low" | "normal" | "high" | "urgent";

export interface UrgencyMeterProps {
  priority: UrgencyName;
  className?: string;
}

/**
 * Three rising bars and the word.
 *
 * Priority was plain text, which made "low" and "urgent" the same size, the
 * same colour and the same weight — so scanning a queue for what to do next
 * meant reading every row instead of glancing down a column.
 *
 * Bars AND the word, never bars alone: a meter with no label is a puzzle for
 * anyone using a screen reader, and three small marks are indistinguishable in
 * greyscale. `urgent` additionally gets a filled chip, because it is the one
 * level that should interrupt a scan.
 */
export function UrgencyMeter({ priority, className }: UrgencyMeterProps) {
  const t = useTranslations("tickets.priority");

  return (
    <span
      data-slot="urgency-meter"
      data-priority={priority}
      className={cn("inline-flex items-center gap-[7px] whitespace-nowrap text-[13px]", className)}
    >
      {/*
        Decorative. The word beside it carries the meaning, so announcing three
        empty elements would only add noise.
      */}
      <span aria-hidden="true" className="flex h-[11px] items-end gap-[2px]">
        {[5, 8, 11].map((height, index) => (
          <i
            key={height}
            style={{ height: `${height}px` }}
            className={cn(
              "w-[3px] rounded-[1px]",
              LIT[priority] > index ? FILL[priority] : "bg-urgency-bar-empty",
            )}
          />
        ))}
      </span>

      <span className={cn(priority === "urgent" && URGENT_CHIP)}>{t(priority)}</span>
    </span>
  );
}

/** How many of the three bars are lit. */
const LIT: Record<UrgencyName, number> = { low: 1, normal: 2, high: 3, urgent: 3 };

const FILL: Record<UrgencyName, string> = {
  low: "bg-urgency-low",
  normal: "bg-urgency-normal",
  high: "bg-urgency-high",
  urgent: "bg-urgency-urgent",
};

/*
 * Urgent is the only level that gets a background. Giving all four a chip
 * would flatten them back into one another, which is the problem the meter
 * exists to solve.
 */
const URGENT_CHIP =
  "rounded-xs border border-clock-breached-border bg-urgency-urgent-bg px-[5px] leading-[18px] text-urgency-urgent";
