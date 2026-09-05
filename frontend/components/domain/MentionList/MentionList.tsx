"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import type { PersonalMention } from "@/lib/api/personal";
import { useFormat } from "@/lib/format/useFormat";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface MentionListProps {
  mentions: PersonalMention[];
  /** Opens the ticket AT the note — the whole content of a mention. */
  onOpen: (ticketId: string, messageId: string) => void;
  onMarkRead: (id: string) => Promise<void>;
}

/**
 * "Somebody asked for you by name."
 *
 * Each row opens its ticket AT the note that named you. Landing at the top of
 * a long thread would leave you searching for the one sentence the
 * notification was about.
 *
 * A quoted extract rather than the whole note: this is a queue, and a long
 * internal note pasted into it pushes every other row off the screen.
 */
export function MentionList({ mentions, onOpen, onMarkRead }: MentionListProps) {
  const t = useTranslations("home.mentions");
  const format = useFormat();

  const [busy, setBusy] = useState<string | null>(null);

  if (mentions.length === 0) {
    return <EmptyState headline={t("empty")} description={t("emptyBody")} />;
  }

  return (
    <ul className="flex flex-col divide-y divide-border-subtle" data-slot="mention-list">
      {mentions.map((mention) => (
        <li
          key={mention.id}
          data-slot="mention-row"
          data-read={mention.read_at !== null}
          className="flex flex-col gap-1.5 py-3"
        >
          <p className="flex flex-wrap items-baseline gap-1 text-sm text-fg-default">
            <b>{mention.author_name}</b>
            <span className="text-fg-muted">{t("mentionedYouOn")}</span>
            <span className="font-medium">{mention.reference}</span>
            <span className="text-fg-muted truncate" dir="auto">
              {mention.subject}
            </span>
            <span className="text-xs text-fg-subtle">
              {format.dateTime(new Date(mention.mentioned_at))}
            </span>
          </p>

          {/* The start rule marks a quotation, not a decoration — the same one
              the notes lane uses, so a quote reads the same everywhere. */}
          <p
            className="border-s-2 border-border-default ps-2 text-sm text-fg-muted"
            dir="auto"
            data-slot="mention-quote"
          >
            {mention.excerpt}
          </p>

          <div className="flex flex-wrap gap-3 text-xs">
            <button
              type="button"
              onClick={() => onOpen(mention.ticket_id, mention.message_id)}
              className={cn("underline text-fg-default", TOUCH_TARGET)}
              data-slot="mention-open"
            >
              {t("open", { reference: mention.reference })}
            </button>

            {mention.read_at === null && (
              <button
                type="button"
                disabled={busy === mention.id}
                onClick={async () => {
                  setBusy(mention.id);
                  try {
                    await onMarkRead(mention.id);
                  } finally {
                    setBusy(null);
                  }
                }}
                className={cn("underline text-fg-muted", TOUCH_TARGET)}
                data-slot="mention-read"
              >
                {t("markRead")}
              </button>
            )}
          </div>
        </li>
      ))}
    </ul>
  );
}
