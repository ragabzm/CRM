import { Inbox } from "lucide-react";
import * as React from "react";

import { cn } from "@/lib/utils";

export interface EmptyStateProps {
  /** "No data for this selection" — about the *selection*, not the reader. */
  headline: string;
  /** Names what was searched for, so the reader can see which filter is too narrow. */
  description?: React.ReactNode;
  /** Ways to loosen the query. All secondary, all reversible. */
  actions?: React.ReactNode;
  /**
   * A real count, printed. Zero is an answer, and saying "0 of 1,284" tells the
   * reader the query ran and came back empty.
   *
   * This is the deliberate opposite of ForbiddenState, which must print no
   * numeral at all.
   */
  count?: React.ReactNode;
  icon?: React.ReactNode;
  className?: string;
}

/**
 * "Nothing happened."
 *
 * Board R-6 draws this beside ForbiddenState and lists nine differences between
 * them — none of which is colour. The ones that live in this component:
 *
 *   1 Layout   centred in the content area, symmetrical, on the vertical axis
 *   2 Mark     an OUTLINED tray on a sunken surface with a hairline border
 *   3 Number   a real count, printed
 *   4 Heading  about the selection
 *   6 Actions  several, secondary, instant, reversible
 *   7/8        no reference code, nothing logged
 *
 * Centred composition is the first thing read, before any word or icon: centred
 * reads as *a space*, where the edge-anchored ForbiddenState reads as *a notice*.
 */
export function EmptyState({
  headline,
  description,
  actions,
  count,
  icon,
  className,
}: EmptyStateProps) {
  return (
    <section
      data-slot="empty-state"
      data-state-kind="empty"
      aria-label="No results"
      className={cn(
        // 1 · Layout — centred and symmetrical.
        "flex flex-col items-center justify-center gap-3 px-6 py-12 text-center",
        className,
      )}
    >
      {/* 2 · Mark — outlined, light, on a sunken surface. */}
      <div
        aria-hidden="true"
        className="flex size-11 items-center justify-center rounded-md border border-border-default bg-surface-sunken text-fg-subtle"
      >
        {icon ?? <Inbox className="size-5" />}
      </div>

      {/* 3 · The number — printed, because zero is an answer. */}
      {count !== undefined && (
        <p className="text-sm text-fg-muted" data-numeric="true">
          {count}
        </p>
      )}

      {/* 4 · Heading — about the selection. */}
      <h2 className="text-lg font-semibold text-fg-default">{headline}</h2>

      {/* 5 · Body — names the filters, so the reader can see what is too narrow. */}
      {description && <p className="max-w-prose text-sm text-fg-muted">{description}</p>}

      {/* 6 · Actions — several, all reversible. */}
      {actions && <div className="mt-1 flex flex-wrap items-center justify-center gap-2">{actions}</div>}
    </section>
  );
}
