"use client";

import { useTranslations } from "next-intl";
import { useCallback, useEffect, useState } from "react";

import { DestructiveConfirm } from "@/components/domain/DestructiveConfirm/DestructiveConfirm";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { RowActions } from "@/components/domain/RowActions/RowActions";
import { Button } from "@/components/ui/button";
import { addNote, deleteNote, listNotes, updateNote, type CustomerNote } from "@/lib/api/notes";
import { ApiError } from "@/lib/api/request";
import { useFormat } from "@/lib/format/useFormat";

export interface NotesLaneProps {
  customerId: string;
  /** The signed-in user's id, for deciding what to offer. */
  currentUserId: string | null;
  /** Whether this person may remove a colleague's note. */
  canModerate: boolean;
}

/**
 * What colleagues have written about a customer.
 *
 * Edit is offered only to the author. Delete is offered to the author and to
 * anyone who can moderate — and both are only ever OFFERS: the server decides,
 * and it decides the same way whatever this component renders.
 */
export function NotesLane({ customerId, currentUserId, canModerate }: NotesLaneProps) {
  const t = useTranslations("notes");
  const format = useFormat();

  const [notes, setNotes] = useState<CustomerNote[]>([]);
  const [draft, setDraft] = useState("");
  const [editing, setEditing] = useState<CustomerNote | null>(null);
  const [editDraft, setEditDraft] = useState("");
  const [pendingDelete, setPendingDelete] = useState<CustomerNote | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    listNotes(customerId)
      .then((loaded) => {
        setNotes(loaded);
        setForbidden(false);
      })
      .catch((caught: unknown) => {
        if (caught instanceof ApiError && caught.status === 403) {
          setForbidden(true);

          return;
        }

        setError(t("loadError"));
      });
  }, [customerId, t]);

  useEffect(load, [load]);

  async function run(action: () => Promise<unknown>) {
    setBusy(true);
    setError(null);

    try {
      await action();
      load();
    } catch (caught) {
      setError(caught instanceof ApiError ? (caught.problem?.detail ?? t("error")) : t("error"));
    } finally {
      setBusy(false);
    }
  }

  if (forbidden) {
    return <ForbiddenState headline={t("forbidden.title")} description={t("forbidden.body")} />;
  }

  return (
    <section className="flex flex-col gap-4" data-slot="notes-lane">
      <h2 className="text-base font-semibold text-fg-default">{t("title")}</h2>

      <form
        className="flex flex-col gap-2"
        onSubmit={(event) => {
          event.preventDefault();

          if (draft.trim() === "") {
            setError(t("required"));

            return;
          }

          void run(async () => {
            await addNote(customerId, draft.trim());
            setDraft("");
          });
        }}
      >
        <label className="sr-only" htmlFor="note-composer">
          {t("add")}
        </label>
        <textarea
          id="note-composer"
          rows={3}
          value={draft}
          placeholder={t("placeholder")}
          maxLength={5000}
          onChange={(event) => setDraft(event.target.value)}
          className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm text-fg-default"
        />
        <Button type="submit" className="w-fit" disabled={busy}>
          {t("add")}
        </Button>
      </form>

      {error && <FormAlert tone="error">{error}</FormAlert>}

      {notes.length === 0 ? (
        <EmptyState headline={t("empty")} description={t("emptyBody")} />
      ) : (
        <ul className="flex flex-col gap-3">
          {notes.map((note) => {
            const isAuthor = currentUserId !== null && note.author.id === currentUserId;
            const actions = [
              ...(isAuthor
                ? [
                    {
                      id: "edit",
                      label: t("edit"),
                      onSelect: () => {
                        setEditing(note);
                        setEditDraft(note.body);
                      },
                    },
                  ]
                : []),
              ...(isAuthor || canModerate
                ? [
                    {
                      id: "delete",
                      label: t("delete"),
                      onSelect: () => setPendingDelete(note),
                      separated: true,
                      destructive: true,
                    },
                  ]
                : []),
            ];

            return (
              <li
                key={note.id}
                data-note-id={note.id}
                className="flex flex-col gap-2 rounded-md border border-border-default bg-surface-base p-3"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex flex-col">
                    <span className="text-sm font-medium text-fg-default">{note.author.name}</span>
                    <span className="text-xs text-fg-muted">
                      {note.created_at ? format.relativeTime(note.created_at, new Date()) : null}
                      {note.edited && (
                        // Says the text is not what was originally written,
                        // which is the whole reason edits are the author's only.
                        <span className="ps-2">({t("edited")})</span>
                      )}
                    </span>
                  </div>

                  {/* Rendered only when there is something to offer — an empty
                      menu is a control that teaches nothing. */}
                  {actions.length > 0 && (
                    <RowActions rowLabel={note.author.name} actions={actions} />
                  )}
                </div>

                {editing?.id === note.id ? (
                  <form
                    className="flex flex-col gap-2"
                    onSubmit={(event) => {
                      event.preventDefault();

                      void run(async () => {
                        await updateNote(note.id, editDraft.trim());
                        setEditing(null);
                      });
                    }}
                  >
                    <label className="sr-only" htmlFor={`note-edit-${note.id}`}>
                      {t("edit")}
                    </label>
                    <textarea
                      id={`note-edit-${note.id}`}
                      rows={3}
                      value={editDraft}
                      maxLength={5000}
                      onChange={(event) => setEditDraft(event.target.value)}
                      className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
                    />
                    <div className="flex gap-2">
                      <Button type="submit" disabled={busy}>
                        {t("save")}
                      </Button>
                      <Button type="button" variant="secondary" onClick={() => setEditing(null)}>
                        {t("cancel")}
                      </Button>
                    </div>
                  </form>
                ) : (
                  <p className="whitespace-pre-wrap text-sm text-fg-default">{note.body}</p>
                )}
              </li>
            );
          })}
        </ul>
      )}

      <DestructiveConfirm
        open={pendingDelete !== null}
        onOpenChange={(open) => !open && setPendingDelete(null)}
        consequence={t("confirmDelete", { author: pendingDelete?.author.name ?? "" })}
        confirmLabel={t("delete")}
        busy={busy}
        onConfirm={() => {
          const target = pendingDelete;
          if (!target) return;

          void run(async () => {
            await deleteNote(target.id);
            setPendingDelete(null);
          });
        }}
      />
    </section>
  );
}
