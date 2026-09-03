import { CheckCircle2, TriangleAlert } from "lucide-react";
import type { ReactNode } from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export interface FormAlertProps {
  tone: "error" | "success";
  children: ReactNode;
  className?: string;
  /**
   * A way out of the failure, rendered inside the banner.
   *
   * Here rather than beside it because a reader who has just been told
   * something went wrong should not have to look elsewhere for the retry — and
   * because a banner that reports a failure with no next step is a dead end.
   */
  action?: { label: string; onSelect: () => void };
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
export function FormAlert({ tone, children, className, action }: FormAlertProps) {
  const Glyph = tone === "error" ? TriangleAlert : CheckCircle2;

  return (
    <div
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
        className={cn(
          "mt-0.5 size-4 shrink-0",
          tone === "error" ? "text-state-danger" : "text-state-success",
        )}
      />
      <span className="flex flex-wrap items-center gap-3">
        <span>{children}</span>

        {action && (
          <Button variant="secondary" size="sm" onClick={action.onSelect}>
            {action.label}
          </Button>
        )}
      </span>
    </div>
  );
}
