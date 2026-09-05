"use client";

import { useTranslations } from "next-intl";
import { useEffect, useRef, useState, type FormEvent } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { ApiError } from "@/lib/api/errors";
import {
  startWebFormSession,
  submitWebForm,
  uploadWebFormAttachment,
  type WebFormAttachment,
  type WebFormCategory,
} from "@/lib/portal/webForm";
import { TOUCH_TARGET } from "@/lib/utils";

/**
 * Asking for help without an account.
 *
 * Six fields, fixed by the story and deliberately not configurable: name,
 * email-or-phone, subject, category, message, attachment. Every extra field is
 * a reason somebody abandons a form they only opened because something had
 * already gone wrong.
 *
 * Two of the things on this page are not fields at all. `hp_company` is a
 * honeypot — off-screen, untabbable, never announced — and `rendered_at` is
 * when the page was drawn, so the server can refuse a submission faster than a
 * person could have typed it. Neither is visible and neither is validated in a
 * way that tells a robot it was caught.
 */
export function PublicRequestFormScreen() {
  const t = useTranslations("webForm");

  const [form, setForm] = useState({
    name: "",
    contact: "",
    subject: "",
    category_id: "",
    message: "",
    hp_company: "",
  });

  const [categories, setCategories] = useState<WebFormCategory[]>([]);
  const [sessionToken, setSessionToken] = useState<string | null>(null);
  const [attachments, setAttachments] = useState<WebFormAttachment[]>([]);
  const [uploading, setUploading] = useState(false);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reference, setReference] = useState<string | null>(null);

  /*
   * Captured on mount, sent back on submit.
   *
   * A ref rather than state: it never changes and never needs to re-render
   * anything, and holding it in state would make the first paint depend on it.
   */
  const renderedAt = useRef<string>(new Date().toISOString());

  useEffect(() => {
    let cancelled = false;

    startWebFormSession()
      .then((session) => {
        if (cancelled) return;
        setSessionToken(session.session_token);
        setCategories(session.categories);
      })
      .catch(() => {
        /*
         * Silent, and the form still works.
         *
         * The token is only needed to attach a file. A person who came here to
         * type a sentence should not be shown an error about a feature they
         * were not going to use.
         */
      });

    return () => {
      cancelled = true;
    };
  }, []);

  function set(field: keyof typeof form, value: string) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  async function attach(file: File | undefined) {
    if (file === undefined || sessionToken === null) return;

    setUploading(true);
    setError(null);

    try {
      const uploaded = await uploadWebFormAttachment(file, sessionToken);
      setAttachments((current) => [...current, uploaded]);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : t("genericError"));
    } finally {
      setUploading(false);
    }
  }

  async function submit(event: FormEvent) {
    event.preventDefault();

    setPending(true);
    setError(null);

    try {
      const result = await submitWebForm({
        name: form.name,
        contact: form.contact,
        subject: form.subject,
        category_id: Number(form.category_id),
        message: form.message,
        hp_company: form.hp_company,
        rendered_at: renderedAt.current,
        ...(attachments.length > 0
          ? {
              attachment_ids: attachments.map((a) => a.id),
              ...(sessionToken !== null ? { session_token: sessionToken } : {}),
            }
          : {}),
      });

      setReference(result.reference);
    } catch (caught) {
      /*
       * The server's own words wherever it gave any. "Please take a moment and
       * try again" and "you have sent several already" are different problems
       * with different answers, and a single generic message turns both into a
       * form the person tries again unchanged.
       */
      setError(
        caught instanceof ApiError
          ? (caught.problem?.detail ?? t("genericError"))
          : t("genericError"),
      );
    } finally {
      setPending(false);
    }
  }

  if (reference !== null) {
    return (
      <div className="flex w-full max-w-sm flex-col gap-3" data-slot="web-form-submitted">
        <h1 className="text-xl font-semibold text-fg-default">{t("submittedTitle")}</h1>
        <p className="text-sm text-fg-muted">{t("submittedBody")}</p>
        {/*
          The reference, and nothing else. No ticket view is exposed publicly:
          anybody holding a reference could otherwise read a stranger's
          conversation.
        */}
        <p className="text-sm text-fg-default">
          {t("reference")} <span className="font-mono font-semibold">{reference}</span>
        </p>
      </div>
    );
  }

  return (
    <form onSubmit={submit} className="flex w-full max-w-sm flex-col gap-4" data-slot="web-form">
      <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>
      <p className="text-sm text-fg-muted">{t("hint")}</p>

      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      <FormField
        label={t("name")}
        name="name"
        autoComplete="name"
        required
        value={form.name}
        onChange={(event) => set("name", event.target.value)}
      />

      <FormField
        label={t("contact")}
        name="contact"
        hint={t("contactHint")}
        autoComplete="email"
        required
        value={form.contact}
        onChange={(event) => set("contact", event.target.value)}
      />

      <FormField
        label={t("subject")}
        name="subject"
        required
        value={form.subject}
        onChange={(event) => set("subject", event.target.value)}
      />

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-fg-default">{t("category")}</span>
        <select
          name="category_id"
          required
          value={form.category_id}
          onChange={(event) => set("category_id", event.target.value)}
          className="h-11 rounded-md border border-border-default bg-surface-default px-3 text-sm text-fg-default"
        >
          <option value="">{t("categoryPlaceholder")}</option>
          {categories.map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </select>
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-fg-default">{t("message")}</span>
        <textarea
          name="message"
          required
          rows={5}
          value={form.message}
          onChange={(event) => set("message", event.target.value)}
          className="rounded-md border border-border-default bg-surface-default p-3 text-sm text-fg-default"
        />
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-fg-default">{t("attachment")}</span>
        <input
          type="file"
          name="attachment"
          disabled={sessionToken === null || uploading}
          onChange={(event) => void attach(event.target.files?.[0])}
          className={`text-sm text-fg-muted ${TOUCH_TARGET}`}
        />
      </label>

      {attachments.length > 0 && (
        <ul className="flex flex-col gap-1 text-sm" data-slot="web-form-attachments">
          {attachments.map((attachment) => (
            <li key={attachment.id} className="flex items-center justify-between gap-2">
              <span className="truncate text-fg-default">{attachment.filename}</span>
              {/*
                A chip, not a link. The file has not been scanned yet, and a
                download offered before the verdict is a download of something
                nobody has checked.
              */}
              <span className="shrink-0 rounded-full bg-surface-muted px-2 py-0.5 text-xs text-fg-muted">
                {attachment.downloadable ? t("scanClean") : t("scanPending")}
              </span>
            </li>
          ))}
        </ul>
      )}

      {/*
        The honeypot.
        Off-screen rather than `display: none`: a robot that reads styles skips
        a hidden field and fills a visible one. Untabbable and unannounced, so
        nobody using a keyboard or a screen reader ever lands on it.

        `start-`, not `left-`: in Arabic the page flows the other way and a
        physical offset would park the field in the middle of the layout.
      */}
      <input
        type="text"
        name="hp_company"
        value={form.hp_company}
        onChange={(event) => set("hp_company", event.target.value)}
        tabIndex={-1}
        autoComplete="off"
        aria-hidden="true"
        className="absolute start-[-9999px] h-px w-px opacity-0"
      />

      <SubmitButton pending={pending} pendingLabel={t("sending")}>
        {t("send")}
      </SubmitButton>
    </form>
  );
}
