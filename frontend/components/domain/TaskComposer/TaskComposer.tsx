"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { ApiError } from "@/lib/api/errors";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface TaskComposerProps {
  /** Present when the task is being written from a ticket. */
  ticketId?: string;
  onCreate: (input: { title: string; ticket_id?: string; due_at?: string }) => Promise<void>;
}

/**
 * Writing down the thing that must not be forgotten.
 *
 * Two fields, and the second is optional. There is no description, no
 * assignee, no sub-task and no recurrence — a task with a form is a task
 * nobody writes down, and the whole point is that it costs less than a sticky
 * note.
 *
 * The due date is `datetime-local`, which every mobile browser renders as its
 * own native picker. A bespoke calendar would be one more thing to make work
 * at 390px and in Arabic.
 */
export function TaskComposer({ ticketId, onCreate }: TaskComposerProps) {
  const t = useTranslations("home.tasks");

  const [title, setTitle] = useState("");
  const [dueAt, setDueAt] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit() {
    setBusy(true);
    setError(null);

    try {
      await onCreate({
        title,
        ...(ticketId === undefined ? {} : { ticket_id: ticketId }),
        /*
         * `datetime-local` gives a wall-clock string with no zone. Converting
         * through Date here means the server is told the moment the agent
         * meant in their own timezone, rather than a string it has to guess at.
         */
        ...(dueAt === "" ? {} : { due_at: new Date(dueAt).toISOString() }),
      });

      setTitle("");
      setDueAt("");
    } catch (caught) {
      setError(caught instanceof ApiError ? (caught.problem?.detail ?? t("failed")) : t("failed"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <form
      className="flex flex-wrap items-end gap-2"
      data-slot="task-composer"
      onSubmit={(event) => {
        event.preventDefault();
        void submit();
      }}
    >
      {error !== null && (
        <div className="w-full">
          <FormAlert tone="error">{error}</FormAlert>
        </div>
      )}

      <label className="flex min-w-0 flex-1 basis-48 flex-col gap-1 text-sm">
        <span className="text-fg-muted">{t("titleLabel")}</span>
        <input
          type="text"
          value={title}
          dir="auto"
          maxLength={200}
          onChange={(event) => setTitle(event.target.value)}
          className={cn(
            "rounded-md border border-border-default bg-surface-default px-2 py-1.5 text-sm",
            TOUCH_TARGET,
          )}
        />
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="text-fg-muted">{t("dueLabel")}</span>
        <input
          type="datetime-local"
          value={dueAt}
          onChange={(event) => setDueAt(event.target.value)}
          className={cn(
            "rounded-md border border-border-default bg-surface-default px-2 py-1.5 text-sm",
            TOUCH_TARGET,
          )}
        />
      </label>

      <SubmitButton pending={busy} disabled={title.trim() === ""}>
        {t("add")}
      </SubmitButton>
    </form>
  );
}
