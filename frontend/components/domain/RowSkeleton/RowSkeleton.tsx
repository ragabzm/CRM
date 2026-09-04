import { cn } from "@/lib/utils";

export interface RowSkeletonProps {
  /** How many placeholder rows to draw. */
  rows?: number;
  /** Announced once, instead of the rows themselves. */
  label: string;
  className?: string;
}

/**
 * The shape of an answer that has not arrived.
 *
 * The list used to render its EMPTY state on first paint — "No tickets match
 * these filters" — while the request was still in flight, and the counts strip
 * showed five dashes reading "Not tracked yet" about a feature that works. The
 * screen was answering a question nobody had asked yet, and then changing its
 * mind a second later.
 *
 * Same height as a real row on purpose: a skeleton that is the wrong size
 * makes the page jump when the data lands, which is the other half of the same
 * problem.
 *
 * One polite announcement, not one per row: a screen reader listing eight
 * empty placeholders tells somebody nothing except that they should wait,
 * eight times.
 */
export function RowSkeleton({ rows = 6, label, className }: RowSkeletonProps) {
  return (
    <div data-slot="row-skeleton" className={cn("flex flex-col gap-px", className)}>
      <p role="status" className="sr-only">
        {label}
      </p>

      {Array.from({ length: rows }, (_, index) => (
        <div
          key={index}
          aria-hidden="true"
          className="flex h-11 items-center gap-3 border-b border-border-subtle px-2"
        >
          <span className="h-3 w-2/5 animate-pulse rounded-sm bg-surface-sunken" />
          <span className="h-3 w-16 animate-pulse rounded-sm bg-surface-sunken" />
          <span className="h-3 w-14 animate-pulse rounded-sm bg-surface-sunken" />
          <span className="ms-auto h-3 w-20 animate-pulse rounded-sm bg-surface-sunken" />
        </div>
      ))}
    </div>
  );
}
