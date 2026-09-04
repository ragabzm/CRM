import { useTranslations } from "next-intl";

import { cn } from "@/lib/utils";

export type TicketStatusName = "new" | "open" | "pending" | "resolved" | "closed" | "cancelled";

export interface StatusBadgeProps {
  status: TicketStatusName;
  className?: string;
}

/**
 * The one coloured thing in a ticket row.
 *
 * The list showed every status as plain black text, so an open ticket and a
 * closed one carried the same visual weight and the eye had nothing to catch
 * on. The mockup's rule is explicit — **one badge per row** — which only works
 * if there IS a badge: the single spot of colour is what the whole row is
 * arranged around.
 *
 * The dot is not decoration. It gives the badge a shape that survives
 * greyscale, a colour-blind reader and Windows High Contrast Mode, none of
 * which can see the difference between the green and amber backgrounds.
 *
 * Colour comes from `ticket-*`, the semantic alias for the status palette.
 * That palette existed from the first commit and no component could reach it:
 * the primitive names are banned in components, and there was no alias.
 */
export function StatusBadge({ status, className }: StatusBadgeProps) {
  const t = useTranslations("tickets.status");

  return (
    <span
      data-slot="status-badge"
      data-status={status}
      className={cn(
        "inline-flex h-[22px] items-center gap-1.5 whitespace-nowrap rounded-sm border px-2 text-[13px] font-medium",
        TREATMENT[status],
        className,
      )}
    >
      <svg
        aria-hidden="true"
        viewBox="0 0 16 16"
        className="size-2.5 shrink-0"
        fill="none"
        stroke="currentColor"
        strokeWidth={2}
      >
        <circle cx="8" cy="8" r="4.4" />
      </svg>
      {t(status)}
    </span>
  );
}

/**
 * Foreground, background and border together, per status.
 *
 * A record rather than a template string, so a status with no treatment is a
 * type error rather than a badge that renders with no colour at all.
 */
const TREATMENT: Record<TicketStatusName, string> = {
  new: "text-ticket-new bg-ticket-new-bg border-ticket-new-border",
  open: "text-ticket-open bg-ticket-open-bg border-ticket-open-border",
  pending: "text-ticket-pending bg-ticket-pending-bg border-ticket-pending-border",
  resolved: "text-ticket-resolved bg-ticket-resolved-bg border-ticket-resolved-border",
  closed: "text-ticket-closed bg-ticket-closed-bg border-ticket-closed-border",
  cancelled: "text-ticket-cancelled bg-ticket-cancelled-bg border-ticket-cancelled-border",
};
