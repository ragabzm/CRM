"use client";

import type { ReactNode } from "react";

import { Button } from "@/components/ui/button";

export interface SubmitButtonProps {
  children: ReactNode;
  /** Shown while the request is in flight; also disables the control. */
  pending?: boolean;
  pendingLabel?: ReactNode;
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
  variant = "primary",
  className,
}: SubmitButtonProps) {
  return (
    <Button
      type="submit"
      variant={variant}
      disabled={pending}
      aria-busy={pending || undefined}
      {...(className ? { className } : {})}
    >
      {pending ? (pendingLabel ?? children) : children}
    </Button>
  );
}
