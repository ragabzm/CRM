import { Lock } from "lucide-react";
import * as React from "react";

import { cn } from "@/lib/utils";

/**
 * Marks the region that must never contain a digit. The UX-06 leak guard greps
 * inside this attribute; the reference/audit block sits outside it on purpose
 * (see the note on `reference` below).
 */
export const FORBIDDEN_NO_NUMERAL_REGION = "forbidden-no-numeral";

export interface ForbiddenStateProps {
  /** "You do not have access to this data" — about the *reader*, not the selection. */
  headline: string;
  /** Names the scope boundary and who holds it. */
  description?: React.ReactNode;
  /** What the withheld figure is called, e.g. "tickets". */
  withheldLabel?: string;
  /** The single primary action, which leaves the product and involves a person. */
  action?: React.ReactNode;
  /** A way back to safety. */
  secondaryAction?: React.ReactNode;
  /**
   * Support handle, e.g. "ERR-SCOPE-403 · ref 7K2-4801". Board R-6 requires it:
   * a code turns "it says I can't" into a conversation an administrator can
   * actually resolve.
   *
   * It renders OUTSIDE the no-numeral region, because the digits in an error
   * code are not a count. UX-06 forbids leaking *how many* records exist, not
   * every digit on the page. With no `reference` and no `auditNote` supplied,
   * this component renders no numeral at all.
   */
  reference?: string;
  /** "Recorded in the audit log at …" — routine, not an accusation. */
  auditNote?: React.ReactNode;
  className?: string;
}

/*
 * There is deliberately NO `count` prop, and no way to pass one.
 *
 * This is the load-bearing difference from EmptyState. Zero is an answer; the
 * em dash is a refusal to answer. A permission surface that prints `0` has both
 * leaked that the count is small AND told a lie — how many tickets the reader
 * is not scoped to see is itself information they are not scoped to see.
 *
 * If a future caller "just needs" a count here, that is a signal they want
 * EmptyState, not a signal to add the prop.
 */

/**
 * "You may not see this."
 *
 * Board R-6 draws this beside EmptyState and lists nine differences between
 * them — not one of which is colour. The ones that live in this component:
 *
 *   1 Layout   flush to the inline-start edge, asymmetric, with a solid rule
 *              down its leading edge. Edge-anchored reads as *a notice*, where
 *              EmptyState's centred composition reads as *a space*.
 *   2 Mark     a FILLED DARK badge carrying a closed padlock in white — solid
 *              against EmptyState's outline, dark against its light. Visible at
 *              a distance, in greyscale, and out of focus.
 *   3 Number   an em dash, in mono, labelled "not disclosed".
 *   4 Heading  about the reader.
 *   5 Body     names the scope boundary and who holds it.
 *   6 Actions  ONE primary action that involves another person, plus a way back.
 *   7 Reference a quotable support code.
 *   8 Audit    a logged line, shown, with the reassurance that it is routine.
 */
export function ForbiddenState({
  headline,
  description,
  withheldLabel,
  action,
  secondaryAction,
  reference,
  auditNote,
  className,
}: ForbiddenStateProps) {
  return (
    <section
      data-slot="forbidden-state"
      data-state-kind="forbidden"
      aria-label="Access denied"
      className={cn(
        // 1 · Layout — edge-anchored and asymmetric, with a leading rule.
        "flex flex-col items-start gap-3 border-s-4 border-fg-default bg-surface-sunken px-6 py-8 text-start",
        className,
      )}
    >
      <div data-region={FORBIDDEN_NO_NUMERAL_REGION} className="flex flex-col items-start gap-3">
        {/* 2 · Mark — filled, dark, padlock in white. */}
        <div
          aria-hidden="true"
          className="flex size-10 items-center justify-center rounded-md bg-surface-inverse text-fg-inverse"
        >
          <Lock className="size-5" />
        </div>

        {/* 3 · The number — withheld. An em dash, never a numeral. */}
        <p className="flex items-baseline gap-1.5 text-sm">
          <span aria-hidden="true" className="mono text-lg text-fg-default">
            &mdash;
          </span>
          {withheldLabel && <span className="text-fg-muted">{withheldLabel}</span>}
          <span className="text-fg-muted">&middot; not disclosed</span>
        </p>

        {/* 4 · Heading — about the reader. */}
        <h2 className="text-lg font-semibold text-fg-default">{headline}</h2>

        {/* 5 · Body — the scope boundary and who holds it. */}
        {description && <p className="max-w-prose text-sm text-fg-muted">{description}</p>}

        {/* 6 · Actions — one primary, plus a way back. */}
        {(action || secondaryAction) && (
          <div className="mt-1 flex flex-wrap items-center gap-2">
            {action}
            {secondaryAction}
          </div>
        )}
      </div>

      {/*
        7 · Reference and 8 · Audit sit OUTSIDE the no-numeral region: an error
        code and a timestamp are a support handle, not a count. See `reference`.
      */}
      {(reference || auditNote) && (
        <div className="flex flex-col gap-1 border-t border-border-default pt-3 text-xs text-fg-subtle">
          {reference && <p className="tref">{reference}</p>}
          {auditNote && <p>{auditNote}</p>}
        </div>
      )}
    </section>
  );
}
