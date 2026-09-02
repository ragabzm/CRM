"use client";

import { useTranslations } from "next-intl";
import { useId, useState, type FormEvent } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { Button } from "@/components/ui/button";
import type { Bilingual, QuickReply } from "@/lib/api/admin";

export interface QuickReplyEditorProps {
  /** Omit to create; supply to edit. */
  reply?: QuickReply;
  onSubmit: (input: { label: Bilingual; body: Bilingual }) => Promise<void>;
  onCancel?: () => void;
}

/**
 * The bilingual editor.
 *
 * Both languages are on screen at once rather than behind tabs. A tabbed editor
 * lets someone fill in English, save, and never see that the Arabic side is
 * empty — the gap then surfaces to an agent mid-conversation, with a customer
 * waiting. Side by side, the empty field is the thing you are looking at.
 */
export function QuickReplyEditor({ reply, onSubmit, onCancel }: QuickReplyEditorProps) {
  const t = useTranslations("admin.quickReply");
  const bodyEnId = useId();
  const bodyArId = useId();

  const [labelEn, setLabelEn] = useState(reply?.label.en ?? "");
  const [labelAr, setLabelAr] = useState(reply?.label.ar ?? "");
  const [bodyEn, setBodyEn] = useState(reply?.body.en ?? "");
  const [bodyAr, setBodyAr] = useState(reply?.body.ar ?? "");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!labelEn.trim() || !labelAr.trim() || !bodyEn.trim() || !bodyAr.trim()) {
      setError(t("bothLanguages"));
      return;
    }

    setError(null);
    setBusy(true);

    try {
      await onSubmit({
        label: { en: labelEn.trim(), ar: labelAr.trim() },
        body: { en: bodyEn.trim(), ar: bodyAr.trim() },
      });
    } catch (caught) {
      const detail =
        caught && typeof caught === "object" && "problem" in caught
          ? ((caught as { problem: { detail?: string } | null }).problem?.detail ?? null)
          : null;
      setError(detail ?? t("bothLanguages"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <form
      onSubmit={submit}
      className="flex flex-col gap-4"
      aria-label={reply ? t("edit") : t("add")}
    >
      <div className="grid gap-4 tablet:grid-cols-2">
        <FormField
          label={t("labelEn")}
          value={labelEn}
          dir="ltr"
          onChange={(event) => setLabelEn(event.target.value)}
        />
        <FormField
          label={t("labelAr")}
          value={labelAr}
          dir="rtl"
          onChange={(event) => setLabelAr(event.target.value)}
        />
      </div>

      <div className="grid gap-4 tablet:grid-cols-2">
        <div className="flex flex-col gap-1">
          <label htmlFor={bodyEnId} className="text-sm font-medium text-fg-default">
            {t("bodyEn")}
          </label>
          {/* Explicit dir on each box: the field's direction follows the
              language of its CONTENT, not the direction of the page. */}
          <textarea
            id={bodyEnId}
            dir="ltr"
            rows={5}
            value={bodyEn}
            onChange={(event) => setBodyEn(event.target.value)}
            className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm text-fg-default"
          />
        </div>

        <div className="flex flex-col gap-1">
          <label htmlFor={bodyArId} className="text-sm font-medium text-fg-default">
            {t("bodyAr")}
          </label>
          <textarea
            id={bodyArId}
            dir="rtl"
            rows={5}
            value={bodyAr}
            onChange={(event) => setBodyAr(event.target.value)}
            className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm text-fg-default"
          />
        </div>
      </div>

      {error && <FormAlert tone="error">{error}</FormAlert>}

      <div className="flex items-center gap-3">
        <Button type="submit" disabled={busy}>
          {t("save")}
        </Button>
        {onCancel && (
          <Button type="button" variant="secondary" onClick={onCancel} disabled={busy}>
            {t("cancel")}
          </Button>
        )}
      </div>
    </form>
  );
}
