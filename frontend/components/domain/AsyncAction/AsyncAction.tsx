"use client";

import { useState } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { Button } from "@/components/ui/button";

export interface AsyncActionProps {
  label: string;
  /** Runs the action. Rejecting renders the failure message. */
  onRun: () => Promise<unknown>;
  successMessage: string;
  failureMessage: string;
  /** Explains what the action does, before it is pressed. */
  hint?: string;
}

/**
 * A button that performs one asynchronous action and reports what happened.
 *
 * Exists because the surrounding wiring — disable while in flight so a second
 * press cannot fire a second action, clear the previous outcome when a new
 * attempt starts, and announce the result through a live region rather than
 * only colouring something — is the part each screen re-implements slightly
 * differently, and the differences are all accessibility regressions.
 */
export function AsyncAction({
  label,
  onRun,
  successMessage,
  failureMessage,
  hint,
}: AsyncActionProps) {
  const [busy, setBusy] = useState(false);
  const [outcome, setOutcome] = useState<"success" | "failure" | null>(null);

  return (
    <div className="flex flex-col gap-2">
      {hint && <p className="text-sm text-fg-muted">{hint}</p>}

      <Button
        className="w-fit"
        disabled={busy}
        onClick={async () => {
          setBusy(true);
          // Clearing first: leaving the previous "Sent" on screen while a new
          // attempt runs is a message about the wrong attempt.
          setOutcome(null);

          try {
            await onRun();
            setOutcome("success");
          } catch {
            setOutcome("failure");
          } finally {
            setBusy(false);
          }
        }}
      >
        {label}
      </Button>

      {outcome && (
        <FormAlert tone={outcome === "success" ? "success" : "error"}>
          {outcome === "success" ? successMessage : failureMessage}
        </FormAlert>
      )}
    </div>
  );
}
