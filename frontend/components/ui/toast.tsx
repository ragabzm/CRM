"use client";

import { cva, type VariantProps } from "class-variance-authority";
import { CheckCircle2, Info, TriangleAlert, X, XCircle } from "lucide-react";
import { Toast as ToastPrimitive } from "radix-ui";
import * as React from "react";

import { cn } from "@/lib/utils";

/**
 * Layer-A toast.
 *
 * shadcn's Radix base does not ship a toast (its CLI redirects to Sonner), so
 * this is built directly on @radix-ui/react-toast — which is what the intake
 * asks for anyway: behaviour and ARIA from Radix, styling from our tokens.
 *
 * UX-03 / NFR-13: each tone renders a distinct glyph, so success/warning/danger
 * /info remain distinguishable in greyscale rather than being four colours of
 * the same rectangle.
 */

const ToastProvider = ToastPrimitive.Provider;

const TONE_ICONS = {
  info: Info,
  success: CheckCircle2,
  warning: TriangleAlert,
  danger: XCircle,
} as const;

export type ToastTone = keyof typeof TONE_ICONS;

const toastVariants = cva(
  [
    "group pointer-events-auto relative flex w-full items-start gap-3",
    "rounded-md border p-4 shadow-menu",
    "data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-80",
  ].join(" "),
  {
    variants: {
      tone: {
        info: "border-state-info-border bg-state-info-bg text-fg-default",
        success: "border-state-success-border bg-state-success-bg text-fg-default",
        warning: "border-state-warning-border bg-state-warning-bg text-fg-default",
        danger: "border-state-danger-border bg-state-danger-bg text-fg-default",
      },
    },
    defaultVariants: { tone: "info" },
  },
);

const TONE_GLYPH_CLASS: Record<ToastTone, string> = {
  info: "text-state-info",
  success: "text-state-success",
  warning: "text-state-warning",
  danger: "text-state-danger",
};

function ToastViewport({
  className,
  ...props
}: React.ComponentProps<typeof ToastPrimitive.Viewport>) {
  return (
    <ToastPrimitive.Viewport
      data-slot="toast-viewport"
      className={cn(
        "fixed top-0 z-100 flex max-h-screen w-full flex-col-reverse gap-2 p-4 sm:bottom-0 sm:top-auto sm:end-0 sm:max-w-100 sm:flex-col",
        className,
      )}
      {...props}
    />
  );
}

function Toast({
  className,
  tone = "info",
  children,
  ...props
}: React.ComponentProps<typeof ToastPrimitive.Root> & VariantProps<typeof toastVariants>) {
  const resolvedTone: ToastTone = tone ?? "info";
  const Glyph = TONE_ICONS[resolvedTone];

  return (
    <ToastPrimitive.Root
      data-slot="toast"
      data-tone={resolvedTone}
      className={cn(toastVariants({ tone: resolvedTone }), className)}
      {...props}
    >
      {/* The glyph is the non-colour half of the signal. */}
      <Glyph aria-hidden="true" className={cn("size-4 shrink-0", TONE_GLYPH_CLASS[resolvedTone])} />
      <div className="flex min-w-0 flex-1 flex-col gap-1">{children}</div>
    </ToastPrimitive.Root>
  );
}

function ToastTitle({ className, ...props }: React.ComponentProps<typeof ToastPrimitive.Title>) {
  return (
    <ToastPrimitive.Title
      data-slot="toast-title"
      className={cn("text-sm font-semibold", className)}
      {...props}
    />
  );
}

function ToastDescription({
  className,
  ...props
}: React.ComponentProps<typeof ToastPrimitive.Description>) {
  return (
    <ToastPrimitive.Description
      data-slot="toast-description"
      className={cn("text-sm text-fg-muted", className)}
      {...props}
    />
  );
}

function ToastAction({ className, ...props }: React.ComponentProps<typeof ToastPrimitive.Action>) {
  return (
    <ToastPrimitive.Action
      data-slot="toast-action"
      className={cn(
        "inline-flex h-7 shrink-0 items-center rounded-md border border-border-strong bg-surface-raised px-2.5 text-xs font-medium hover:bg-surface-hover",
        className,
      )}
      {...props}
    />
  );
}

function ToastClose({ className, ...props }: React.ComponentProps<typeof ToastPrimitive.Close>) {
  return (
    <ToastPrimitive.Close
      data-slot="toast-close"
      aria-label="Dismiss"
      className={cn(
        "absolute top-2 end-2 rounded-sm p-1 text-fg-muted transition-opacity hover:text-fg-default",
        className,
      )}
      {...props}
    >
      <X aria-hidden="true" className="size-4" />
    </ToastPrimitive.Close>
  );
}

export {
  Toast,
  ToastAction,
  ToastClose,
  ToastDescription,
  ToastProvider,
  ToastTitle,
  ToastViewport,
  toastVariants,
};
