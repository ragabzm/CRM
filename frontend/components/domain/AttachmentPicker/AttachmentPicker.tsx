"use client";

import { FileInput } from "@/components/ui/file-input";

export interface AttachmentPickerProps {
  label: string;
  onPick: (file: File) => void;
  /** Names already attached, so somebody can see what they added. */
  attached?: Array<{ id: string; filename: string }>;
  accept?: string;
}

/**
 * Choosing a file, including from a phone's camera.
 *
 * `capture="environment"` asks a mobile browser to offer the camera alongside
 * the photo library. Most of what a customer attaches is a photo of a screen or
 * a receipt taken there and then, and making them leave the form, take it, and
 * come back is exactly where a request gets abandoned. Desktop browsers ignore
 * the hint, so it is always safe to send.
 */
export function AttachmentPicker({
  label,
  onPick,
  attached = [],
  accept = "image/*,application/pdf",
}: AttachmentPickerProps) {
  return (
    <div className="flex flex-col gap-2" data-slot="attachment-picker">
      <FileInput
        label={label}
        accept={accept}
        capture="environment"
        onChange={(event) => {
          const file = event.target.files?.[0];

          if (file) onPick(file);
        }}
      />

      {attached.length > 0 && (
        <ul className="flex flex-col gap-1 text-xs text-fg-muted">
          {attached.map((file) => (
            <li key={file.id}>
              {/* A filename is user-authored and may be in either script. */}
              <bdi dir="auto">{file.filename}</bdi>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
