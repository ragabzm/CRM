"use client";

import { useId, type ComponentProps, type ReactNode } from "react";

import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";

export interface FormFieldProps extends Omit<ComponentProps<"input">, "id"> {
  /** Visible label. Already translated by the caller. */
  label: ReactNode;
  /** Guidance shown under the control. */
  hint?: ReactNode;
  /** Validation message. Its presence marks the field invalid. */
  error?: ReactNode;
}

/**
 * A labelled text field.
 *
 * Exists because the wiring — a generated id, `htmlFor`, `aria-describedby`
 * pointing at both the hint and the error, `aria-invalid` — is the part every
 * screen gets subtly wrong when it assembles a label and an input by hand. Doing
 * it once here is the difference between a form that a screen reader can
 * navigate and one that merely looks right.
 */
export function FormField({ label, hint, error, className, ...props }: FormFieldProps) {
  const id = useId();
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

  return (
    <div className={cn("flex flex-col gap-1", className)}>
      <label htmlFor={id} className="text-sm font-medium text-fg-default">
        {label}
      </label>

      <Input
        {...props}
        id={id}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy}
      />

      {hint && (
        <p id={hintId} className="text-xs text-fg-muted">
          {hint}
        </p>
      )}

      {error && (
        <p id={errorId} className="text-xs text-state-danger">
          {error}
        </p>
      )}
    </div>
  );
}
