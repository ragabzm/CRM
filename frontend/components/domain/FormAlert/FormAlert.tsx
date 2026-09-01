import { CheckCircle2, TriangleAlert } from "lucide-react";
import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

export interface FormAlertProps {
  tone: "error" | "success";
  children: ReactNode;
  className?: string;
}

/**
 * The banner a form uses to report an outcome.
 *
 * The live-region role differs by tone on purpose: an error is `alert`, which
 * assistive technology announces immediately because the reader must act, while
 * a success is `status`, which waits for a pause rather than interrupting.
 *
 * UX-03: the tone is carried by a glyph as well as colour, so it survives
 * greyscale.
 */
export function FormAlert({ tone, children, className }: FormAlertProps) {
  const Glyph = tone === "error" ? TriangleAlert : CheckCircle2;

  return (
    <p
      role={tone === "error" ? "alert" : "status"}
      data-tone={tone}
      className={cn(
        "flex items-start gap-2 rounded-md border px-3 py-2 text-sm text-fg-default",
        tone === "error"
          ? "border-state-danger-border bg-state-danger-bg"
          : "border-state-success-border bg-state-success-bg",
        className,
      )}
    >
      <Glyph
        aria-hidden="true"
        className={cn("mt-0.5 size-4 shrink-0", tone === "error" ? "text-state-danger" : "text-state-success")}
      />
      <span>{children}</span>
    </p>
  );
}
