"use client";

import { FileText, ShieldAlert, ShieldCheck, ShieldQuestion } from "lucide-react";
import { useTranslations } from "next-intl";
import { useCallback, useEffect, useRef, useState } from "react";

import { BidiValue } from "@/components/domain/BidiValue/BidiValue";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { Button } from "@/components/ui/button";
import { FileInput } from "@/components/ui/file-input";
import {
  downloadUrl,
  listAttachments,
  uploadAttachment,
  type Attachment,
  type AttachmentOwnerType,
} from "@/lib/api/attachments";
import { ApiError } from "@/lib/api/request";
import { useFormat } from "@/lib/format/useFormat";

export interface AttachmentsLaneProps {
  ownerType: AttachmentOwnerType;
  ownerId: string;
  /** How often to re-check a pending scan, in ms. */
  pollMs?: number;
}

/** The badge for each scan state. Glyph as well as colour, per UX-03. */
function StatusBadge({ attachment }: { attachment: Attachment }) {
  const t = useTranslations("attachments");

  if (attachment.scan_status === "clean") {
    return (
      <span className="flex items-center gap-1 text-xs text-state-success">
        <ShieldCheck aria-hidden="true" className="size-3.5" />
      </span>
    );
  }

  if (attachment.scan_status === "failed") {
    return (
      <span className="flex items-center gap-1 text-xs text-state-danger">
        <ShieldAlert aria-hidden="true" className="size-3.5" />
        {t("failed")}
      </span>
    );
  }

  return (
    <span className="flex items-center gap-1 text-xs text-fg-muted">
      <ShieldQuestion aria-hidden="true" className="size-3.5" />
      {t("pendingScan")}
    </span>
  );
}

/**
 * Files attached to a record.
 *
 * Never renders a file's contents — not a thumbnail, not a PDF frame, nothing.
 * Displaying uploaded content inline from a trusted origin is a stored XSS that
 * no virus scanner would flag, so the only affordance is a download.
 *
 * A pending file is shown, with its state named and its download withheld. The
 * alternative — hiding it until the scan finishes — makes an upload look like
 * it silently failed.
 */
export function AttachmentsLane({ ownerType, ownerId, pollMs = 3000 }: AttachmentsLaneProps) {
  const t = useTranslations("attachments");
  const format = useFormat();

  const [attachments, setAttachments] = useState<Attachment[]>([]);
  const [forbidden, setForbidden] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);
  const inputRef = useRef<HTMLInputElement | null>(null);

  const load = useCallback(() => {
    listAttachments(ownerType, ownerId)
      .then((loaded) => {
        setAttachments(loaded);
        setForbidden(false);
      })
      .catch((caught: unknown) => {
        if (caught instanceof ApiError && caught.status === 403) {
          setForbidden(true);

          return;
        }

        setError(t("loadError"));
      });
  }, [ownerType, ownerId, t]);

  useEffect(load, [load]);

  const hasPending = attachments.some((attachment) => attachment.scan_status === "pending");

  useEffect(() => {
    // Polls only while something is actually pending, and stops the moment
    // nothing is — a timer that runs forever on a quiet page is a battery bug.
    if (!hasPending) return;

    const timer = setInterval(load, pollMs);

    return () => clearInterval(timer);
  }, [hasPending, pollMs, load]);

  async function upload(file: File) {
    setUploading(true);
    setError(null);

    try {
      await uploadAttachment({ file, ownerType, ownerId });
      load();
    } catch (caught) {
      // The server's reason, which names the actual limit or the actual type.
      setError(caught instanceof ApiError ? (caught.problem?.detail ?? t("error")) : t("error"));
    } finally {
      setUploading(false);

      if (inputRef.current) {
        // Cleared so picking the same file again still fires a change event.
        inputRef.current.value = "";
      }
    }
  }

  if (forbidden) {
    return <ForbiddenState headline={t("forbidden.title")} description={t("forbidden.body")} />;
  }

  return (
    <section className="flex flex-col gap-4" data-slot="attachments-lane">
      <h2 className="text-base font-semibold text-fg-default">{t("title")}</h2>

      <FileInput
        ref={inputRef}
        label={t("add")}
        disabled={uploading}
        onChange={(event) => {
          const file = event.target.files?.[0];

          if (file) void upload(file);
        }}
      />

      {uploading && <p className="text-sm text-fg-muted">{t("uploading")}</p>}
      {error && <FormAlert tone="error">{error}</FormAlert>}

      {attachments.length === 0 ? (
        <EmptyState headline={t("empty")} description={t("emptyBody")} />
      ) : (
        <ul className="flex flex-col gap-2">
          {attachments.map((attachment) => (
            <li
              key={attachment.id}
              data-attachment-id={attachment.id}
              data-scan-status={attachment.scan_status}
              className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border-default bg-surface-base p-3"
            >
              <div className="flex min-w-0 items-center gap-3">
                {/* An icon, never a preview of the file itself. */}
                <FileText aria-hidden="true" className="size-4 shrink-0 text-fg-muted" />

                <div className="flex min-w-0 flex-col">
                  <BidiValue className="truncate text-sm text-fg-default">
                    {attachment.filename}
                  </BidiValue>
                  <span className="flex items-center gap-2 text-xs text-fg-muted">
                    <span>{format.fileSize(attachment.byte_size)}</span>
                    <StatusBadge attachment={attachment} />
                  </span>
                </div>
              </div>

              {attachment.downloadable ? (
                // A plain link: the endpoint answers 302 to a short-lived
                // signed URL, and letting the browser follow it keeps the
                // bytes out of JavaScript entirely.
                <Button asChild variant="secondary">
                  <a href={downloadUrl(attachment.id)} download>
                    {t("download")}
                  </a>
                </Button>
              ) : (
                <div className="flex flex-col items-end gap-1">
                  <Button variant="secondary" disabled aria-describedby={`why-${attachment.id}`}>
                    {t("download")}
                  </Button>
                  {/* Says WHY it is disabled. A greyed-out button with no
                      explanation is a dead end. */}
                  <p
                    id={`why-${attachment.id}`}
                    className="max-w-xs text-end text-xs text-fg-muted"
                  >
                    {attachment.scan_status === "failed"
                      ? (attachment.scan_reason ?? t("failedHint"))
                      : t("pendingScanHint")}
                  </p>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
