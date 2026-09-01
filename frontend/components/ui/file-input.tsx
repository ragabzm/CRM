import { Paperclip } from "lucide-react";
import * as React from "react";

import { cn } from "@/lib/utils";

/**
 * Where the camera should point when `capture` is set.
 *
 * "environment" — the rear camera. Photographing a damaged item, a receipt,
 * a meter reading: what the agent or customer is looking AT.
 * "user"        — the front camera.
 * true          — let the browser choose.
 */
export type CaptureMode = "user" | "environment" | boolean;

export interface FileInputProps extends Omit<React.ComponentProps<"input">, "type" | "capture"> {
  /** Visible label text. Already translated by the caller. */
  label: React.ReactNode;
  /** e.g. "image/*"or".pdf,.png". Filters the picker on every platform. */
  accept?: string;
  /**
   * Asks a mobile browser to offer the camera alongside the photo library.
   * Desktop browsers ignore it, so it is always safe to emit.
   */
  capture?: CaptureMode;
  /** Rendered under the control and wired to the input via aria-describedby. */
  description?: React.ReactNode;
}

/**
 * File upload, including the mobile camera path.
 *
 * A real `<input type="file">` inside a real `<label>`. No hand-rolled
 * drag-and-drop shell: a custom dropzone is invisible to a phone, breaks the
 * camera affordance, and has to reimplement the keyboard and screen-reader
 * behaviour the native control already has. `accept` and `capture` are the
 * whole mobile story, and they are input-only attributes.
 *
 * The `<label>` WRAPS the control rather than only pointing at it, so the
 * entire target is tappable — which matters at 390px where the input itself is
 * a small strip.
 */
function FileInput({
  className,
  label,
  description,
  accept,
  capture,
  id,
  ...props
}: FileInputProps) {
  const generatedId = React.useId();
  const inputId = id ?? generatedId;
  const descriptionId = description ? `${inputId}-description` : undefined;

  return (
    <div className="flex flex-col gap-1.5">
      <label
        htmlFor={inputId}
        data-slot="file-input"
        /*
         * focus-within, not focus: the outline belongs on the label the user
         * sees, while focus actually sits on the input inside it.
         */
        className={cn(
          "flex cursor-pointer items-center gap-2 rounded-lg border border-border-default bg-surface-raised px-2.5 py-1.5 text-sm text-fg-default transition-colors",
          "hover:bg-surface-hover",
          "focus-within:outline-(--focus-ring) focus-within:outline-offset-(--focus-ring-offset)",
          "has-disabled:cursor-not-allowed has-disabled:opacity-50",
          className,
        )}
      >
        <Paperclip aria-hidden="true" className="size-4 shrink-0 text-fg-muted" />
        <span>{label}</span>

        <input
          {...props}
          id={inputId}
          type="file"
          accept={accept}
          capture={capture}
          aria-describedby={descriptionId ?? props["aria-describedby"]}
          /*
           * Visually hidden, NOT display:none — a hidden input is unreachable by
           * keyboard and invisible to assistive technology. This keeps it in the
           * tab order and focusable while the label carries the appearance.
           */
          className="sr-only"
        />
      </label>

      {description && (
        <p id={descriptionId} className="text-xs text-fg-muted">
          {description}
        </p>
      )}
    </div>
  );
}

export { FileInput };
