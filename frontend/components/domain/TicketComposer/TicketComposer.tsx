"use client";

import { useTranslations } from "next-intl";
import { useEffect, useRef, useState } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import { Button } from "@/components/ui/button";
import { FileInput } from "@/components/ui/file-input";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { uploadAttachment, type Attachment } from "@/lib/api/attachments";
import { appendTicketMessage, listQuickReplies, type QuickReply } from "@/lib/api/tickets";

import { useComposerDraft, type ComposerDraft } from "./useComposerDraft";

export interface TicketComposerProps {
  ticketId: string;
  onSent: () => void;
  /** Set by ConversationPanel's "Edit" on a failed send. */
  seedBody?: string | null;
}

/**
 * Where an agent writes.
 *
 * Two things this deliberately never does:
 *
 * 1. **It never carries a version.** Sending is an APPEND. Two colleagues
 *    writing different replies have not conflicted — they have both said
 *    something, and both belong in the thread. Refusing a reply because someone
 *    changed the priority a moment earlier would, on a busy ticket, refuse most
 *    replies, and would train people to retry blindly until one went through.
 *
 * 2. **It never expands a quick reply.** The snippet is inserted as written and
 *    left editable. A composer that silently substituted `{{customer_name}}`
 *    would eventually send "Dear ," to somebody, and the agent who pressed Send
 *    would have had no way to see it coming.
 */
export function TicketComposer({ ticketId, onSent, seedBody }: TicketComposerProps) {
  const t = useTranslations("ticket.composer");
  const { recall, remember, forget, empty } = useComposerDraft(ticketId);

  const textarea = useRef<HTMLTextAreaElement>(null);
  const [draft, setDraft] = useState<ComposerDraft>(empty);
  const [attachments, setAttachments] = useState<Attachment[]>([]);
  const [quickReplies, setQuickReplies] = useState<QuickReply[]>([]);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState(false);

  // Restore what was left behind, once.
  useEffect(() => {
    void Promise.resolve().then(() => {
      const kept = recall();

      if (kept !== null) setDraft(kept);
    });
  }, [recall]);

  useEffect(() => {
    listQuickReplies()
      .then(setQuickReplies)
      // An empty picker is a missing convenience; a broken composer is a
      // missing job. The failure stays quiet.
      .catch(() => undefined);
  }, []);

  // "Edit" on a failed send: put the words back where they can be fixed.
  useEffect(() => {
    if (seedBody === null || seedBody === undefined) return;

    // Deferred by a microtask: setting state straight from an effect body
    // cascades renders, and this one runs whenever a failed row is clicked.
    void Promise.resolve().then(() => {
      setDraft((current) => ({ ...current, body: seedBody }));
      textarea.current?.focus();
    });
  }, [seedBody]);

  function update(next: Partial<ComposerDraft>) {
    setDraft((current) => {
      const merged = { ...current, ...next };

      remember(merged);

      return merged;
    });
  }

  /**
   * Inserts a snippet where the cursor is, and leaves it selected-adjacent.
   *
   * At the caret rather than replacing the box: an agent usually types a line
   * of their own first, and losing it to a template would make the picker
   * something to avoid.
   */
  function insertQuickReply(body: string) {
    const field = textarea.current;
    const at = field?.selectionStart ?? draft.body.length;
    const next = draft.body.slice(0, at) + body + draft.body.slice(at);

    update({ body: next });

    // After the state lands, so the caret is placed in the new text rather
    // than at the end of the old.
    requestAnimationFrame(() => {
      field?.focus();
      field?.setSelectionRange(at + body.length, at + body.length);
    });
  }

  async function attach(file: File) {
    try {
      /*
       * Uploaded against the TICKET, not the message — the message does not
       * exist yet. Sending re-points the file at it. This is also why a slow or
       * refused upload never costs the agent the reply they typed.
       */
      const uploaded = await uploadAttachment({
        file,
        ownerType: "ticket",
        ownerId: ticketId,
      });

      setAttachments((current) => [...current, uploaded]);
      update({ attachmentIds: [...draft.attachmentIds, uploaded.id] });
    } catch {
      setError(true);
    }
  }

  async function send() {
    if (draft.body.trim() === "") return;

    setSending(true);
    setError(false);

    try {
      await appendTicketMessage(
        ticketId,
        draft.body,
        draft.type === "note" ? "internal" : "outbound",
        draft.attachmentIds,
      );

      // Only now. Clearing before the server confirmed would lose the reply if
      // the request failed.
      forget();
      setDraft(empty);
      setAttachments([]);
      onSent();
    } catch {
      // The draft stays exactly where it was, on screen and in storage.
      setError(true);
    } finally {
      setSending(false);
    }
  }

  const note = draft.type === "note";

  return (
    <section data-slot="ticket-composer" className="flex flex-col gap-3">
      <SegmentedFilter
        label={t("reply")}
        value={draft.type}
        options={[
          { value: "reply", label: t("reply") },
          { value: "note", label: t("note") },
        ]}
        onChange={(value) => update({ type: value === "note" ? "note" : "reply" })}
      />

      {note && (
        // Said before they write, not after they send.
        <p className="text-xs font-semibold text-state-warning">
          <InternalBadge />
        </p>
      )}

      <textarea
        ref={textarea}
        dir="auto"
        rows={5}
        aria-label={note ? t("notePlaceholder") : t("placeholder")}
        placeholder={note ? t("notePlaceholder") : t("placeholder")}
        value={draft.body}
        onChange={(event) => update({ body: event.target.value })}
        className="w-full rounded-md border border-border-default bg-surface-base p-3 text-sm text-fg-default"
      />

      {attachments.length > 0 && (
        <ul className="flex flex-col gap-1 text-xs text-fg-muted">
          {attachments.map((attachment) => (
            <li key={attachment.id}>
              <bdi dir="auto">{attachment.filename}</bdi>
            </li>
          ))}
        </ul>
      )}

      {error && <FormAlert tone="error">{`${t("error")} ${t("draftKept")}`}</FormAlert>}

      <div className="flex flex-wrap items-center gap-3">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="secondary" size="sm">
              {t("quickReplies")}
            </Button>
          </DropdownMenuTrigger>

          <DropdownMenuContent>
            {quickReplies.length === 0 ? (
              <DropdownMenuItem disabled>{t("noQuickReplies")}</DropdownMenuItem>
            ) : (
              quickReplies.map((reply) => (
                <DropdownMenuItem
                  key={String(reply.id)}
                  onSelect={() => insertQuickReply(reply.body)}
                >
                  {reply.title}
                </DropdownMenuItem>
              ))
            )}
          </DropdownMenuContent>
        </DropdownMenu>

        <FileInput
          label={t("attach")}
          onChange={(event) => {
            const file = event.target.files?.[0];

            if (file) void attach(file);
          }}
        />

        <Button
          variant="primary"
          onClick={() => void send()}
          disabled={sending || draft.body.trim() === ""}
        >
          {sending ? t("sending") : note ? t("sendNote") : t("send")}
        </Button>
      </div>
    </section>
  );
}

function InternalBadge() {
  const t = useTranslations("ticket.internalNote");

  return <>{t("badge")}</>;
}
