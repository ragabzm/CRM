import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

export interface BidiValueProps {
  children: ReactNode;
  className?: string;
  /**
   * `bdi` by default. It isolates its contents from the surrounding direction
   * in the HTML parser itself, so the run survives even where CSS has not
   * loaded. `span` exists for the rare case where a `bdi` is invalid content
   * for the parent element.
   */
  as?: "span" | "bdi";
}

/**
 * Isolates a left-to-right run inside right-to-left prose.
 *
 * Without this, the Unicode bidirectional algorithm reorders neutral characters
 * at the boundary of a Latin run embedded in Arabic text. `TKT-000123` inside an
 * Arabic sentence renders as `000123-TKT`, and a range like `1-31` reverses to
 * `31-1` — silently, and only in Arabic, which is exactly the kind of bug that
 * ships.
 *
 * Belt and braces on purpose: the `<bdi>` element, an explicit `dir="ltr"`, and
 * `unicode-bidi: isolate` from the `num` utility. Each alone is enough in a
 * modern browser; together they also survive a stripped stylesheet and an
 * unusual parent.
 *
 * Wrap every ticket reference, ULID, phone number, email address, IP address
 * and filename that can appear in prose.
 */
export function BidiValue({ children, className, as: Tag = "bdi" }: BidiValueProps) {
  return (
    <Tag dir="ltr" className={cn("num", className)}>
      {children}
    </Tag>
  );
}
