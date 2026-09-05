"use client";

import type { ReactNode } from "react";

import { Button } from "@/components/ui/button";

export interface SubmitButtonProps {
  children: ReactNode;
  /** Shown while the request is in flight; also disables the control. */
  pending?: boolean;
  pendingLabel?: ReactNode;
  /**
   * Held closed for a reason the form can already see — an empty required
   * field, most often. Separate from `pending`, which is about a request in
   * flight, because the two are cleared by different things.
   */
  disabled?: boolean;
  variant?: "primary" | "secondary";
  className?: string;
}

/**
 * A form's submit control.
 *
 * Disabling while pending is the point: a form that stays submittable invites a
 * second POST, and on a sign-in form that means a second attempt counted against
 * the rate limit for no reason.
 */
export function SubmitButton({
  children,
  pending = false,
  pendingLabel,
  disabled = false,
  variant = "primary",
  className,
}: SubmitButtonProps) {
  return (
    <Button
      type="submit"
      variant={variant}
      disabled={pending || disabled}
      aria-busy={pending || undefined}
      {...(className ? { className } : {})}
    >
      {pending ? (pendingLabel ?? children) : children}
    </Button>
  );
}
