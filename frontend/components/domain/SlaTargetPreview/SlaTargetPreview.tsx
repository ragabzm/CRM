"use client";

import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";

import { useFormat } from "@/lib/format/useFormat";

interface Preview {
  minutes: number;
  from: string;
  due_at: string;
  timezone: string;
}

export interface SlaTargetPreviewProps {
  /** The target being edited, in minutes of working time. */
  minutes: number;
}

/**
 * "Four working hours from now is Tuesday at 11:00."
 *
 * A target expressed as a number of minutes is not something a person can
 * picture. An administrator setting eight hours against a Sunday-to-Thursday,
 * 09:00-17:00 week has to work out in their head that it lands on the next
 * working day — while editing that very schedule on the same screen.
 *
 * The endpoint that answers this was built, published in `openapi.yaml`, typed
 * in the generated client, and called by nothing for a whole story. It uses
 * the SAME calculator the live timers use, so what it shows cannot disagree
 * with what the engine will actually do.
 */
export function SlaTargetPreview({ minutes }: SlaTargetPreviewProps) {
  const t = useTranslations("admin.serviceLevels");
  const format = useFormat();

  const [preview, setPreview] = useState<Preview | null>(null);
  const [failed, setFailed] = useState(false);

  const valid = Number.isFinite(minutes) && minutes >= 1;

  useEffect(() => {
    /*
     * No `setState` on the way out: an invalid draft is derived from the prop,
     * so it is decided at render time below rather than written back into
     * state here, which would cascade a second render for every keystroke.
     */
    if (!valid) return;

    let cancelled = false;

    /*
     * Debounced. The preview follows a number somebody is typing, and a
     * request per keystroke sends one per digit — with the answers arriving
     * out of order, so the last to land wins rather than the last asked for.
     */
    const timer = setTimeout(() => {
      void (async () => {
        try {
          const { request } = await import("@/lib/api/request");

          const body = await request<Preview>(`/admin/sla/preview?minutes=${minutes}`, {
            method: "GET",
          });

          if (!cancelled) {
            setPreview(body);
            setFailed(false);
          }
        } catch {
          if (!cancelled) {
            setPreview(null);
            setFailed(true);
          }
        }
      })();
    }, 400);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [minutes, valid]);

  // Quiet on failure. A preview that will not load is a missing convenience,
  // not a reason to interrupt somebody editing a setting.
  if (!valid || failed || preview === null || preview.minutes !== minutes) return null;

  return (
    <p className="text-xs text-fg-muted" data-slot="sla-preview">
      {t("preview", {
        due: format.dateTime(preview.due_at),
        timezone: preview.timezone,
      })}
    </p>
  );
}
