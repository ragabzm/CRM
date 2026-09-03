"use client";

import { useTranslations } from "next-intl";
import { useState, type FormEvent } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { AttachmentPicker } from "@/components/domain/AttachmentPicker/AttachmentPicker";
import { uploadAttachment } from "@/lib/api/attachments";
import { submitPortalRequest } from "@/lib/portal/api";

export interface PortalNewRequestProps {
  customerId: string;
  onSubmitted: (id: string) => void;
}

/**
 * Asking us something.
 *
 * Two required fields. A customer opening this form has already had a problem;
 * every extra question is a chance to give up and phone instead — and the
 * category they cannot confidently pick is the desk's job to sort anyway.
 */
export function PortalNewRequest({ customerId, onSubmitted }: PortalNewRequestProps) {
  const t = useTranslations("portal.new");
  const detail = useTranslations("portal.detail");

  const [subject, setSubject] = useState("");
  const [description, setDescription] = useState("");
  const [attachments, setAttachments] = useState<Array<{ id: string; filename: string }>>([]);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function attach(file: File) {
    try {
      /*
       * Uploaded against the CUSTOMER, not the request — the request does not
       * exist yet. Submitting moves the files onto it. It also means a slow or
       * refused upload never costs somebody the description they typed.
       */
      const uploaded = await uploadAttachment({
        file,
        ownerType: "customer",
        ownerId: customerId,
      });

      setAttachments((current) => [...current, { id: uploaded.id, filename: uploaded.filename }]);
    } catch {
      setError(t("error"));
    }
  }

  async function submit(event: FormEvent) {
    event.preventDefault();

    setPending(true);
    setError(null);

    try {
      const created = await submitPortalRequest({
        subject,
        description,
        attachment_ids: attachments.map((a) => a.id),
      });

      onSubmitted(created.id);
    } catch {
      setError(t("error"));
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4" data-slot="portal-new-request">
      <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>

      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      <FormField
        label={t("subject")}
        name="subject"
        placeholder={t("subjectPlaceholder")}
        value={subject}
        onChange={(event) => setSubject(event.target.value)}
      />

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-fg-default">{t("description")}</span>

        <textarea
          dir="auto"
          rows={6}
          placeholder={t("descriptionPlaceholder")}
          value={description}
          onChange={(event) => setDescription(event.target.value)}
          className="w-full rounded-md border border-border-default bg-surface-base p-3 text-sm text-fg-default"
        />
      </label>

      <AttachmentPicker
        label={detail("attach")}
        attached={attachments}
        onPick={(file) => void attach(file)}
      />

      <div>
        <SubmitButton variant="primary" pending={pending}>
          {t("submit")}
        </SubmitButton>
      </div>
    </form>
  );
}
