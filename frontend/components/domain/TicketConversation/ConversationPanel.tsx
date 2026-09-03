"use client";

import { useTranslations } from "next-intl";
import { useCallback, useEffect, useState } from "react";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { Button } from "@/components/ui/button";
import { listTicketMessages, retryTicketMessage, type TicketMessage } from "@/lib/api/tickets";
import { useFormat } from "@/lib/format/useFormat";
import { cn } from "@/lib/utils";

export interface ConversationPanelProps {
  ticketId: string;
  /** Re-hydrates the composer with a failed message's body so it can be fixed. */
  onEditFailed: (body: string) => void;
}

/**
 * What was said on this ticket, in order.
 *
 * THREE treatments and no fourth. A reader has to be able to tell, without
 * reading a word, whether a message came from the customer, went to them, or
 * was never meant to leave the building. The third is the one that matters:
 * mistaking an internal note for a reply is how a colleague's private remark
 * gets sent to the person it is about.
 *
 * Each treatment carries its meaning in words as well as colour and position,
 * because colour alone is not a message a screen reader can read.
 */
export function ConversationPanel({ ticketId, onEditFailed }: ConversationPanelProps) {
  const t = useTranslations("ticket.conversation");
  const format = useFormat();

  const [messages, setMessages] = useState<TicketMessage[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [retrying, setRetrying] = useState<string | null>(null);

  const load = useCallback(() => {
    let cancelled = false;

    void Promise.resolve().then(() => {
      if (!cancelled) setLoading(true);
    });

    listTicketMessages(ticketId)
      .then((found) => {
        if (!cancelled) {
          setMessages(found);
          setError(false);
        }
      })
      .catch(() => {
        if (!cancelled) setError(true);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [ticketId]);

  useEffect(load, [load]);

  async function retry(message: TicketMessage) {
    setRetrying(message.id);

    try {
      const updated = await retryTicketMessage(ticketId, message.id);

      setMessages((current) => current.map((m) => (m.id === updated.id ? updated : m)));
    } catch {
      // The row keeps its failed state and its Retry button. Nothing is lost,
      // and the agent can try again or edit instead.
    } finally {
      setRetrying(null);
    }
  }

  if (error) {
    return (
      <FormAlert tone="error" action={{ label: t("retry"), onSelect: load }}>
        {t("empty")}
      </FormAlert>
    );
  }

  if (!loading && messages.length === 0) {
    return <EmptyState headline={t("empty")} description={t("emptyBody")} />;
  }

  return (
    <ol aria-label={t("title")} data-slot="ticket-conversation" className="flex flex-col gap-4">
      {messages.map((message) => (
        <MessageRow
          key={message.id}
          message={message}
          t={t}
          format={format}
          retrying={retrying === message.id}
          onRetry={() => void retry(message)}
          onEdit={() => onEditFailed(message.body)}
        />
      ))}
    </ol>
  );
}

/** Where the row sits and what it looks like — never what it MEANS. */
const TREATMENT: Record<TicketMessage["direction"], string> = {
  inbound: "me-auto border-border-default bg-surface-base",
  outbound: "ms-auto border-accent-border bg-accent-subtle",
  // Full width and visibly not a bubble: a note is not part of the exchange.
  internal: "border-state-warning-border bg-state-warning-bg",
};

function MessageRow({
  message,
  t,
  format,
  retrying,
  onRetry,
  onEdit,
}: {
  message: TicketMessage;
  t: ReturnType<typeof useTranslations<"ticket.conversation">>;
  format: ReturnType<typeof useFormat>;
  retrying: boolean;
  onRetry: () => void;
  onEdit: () => void;
}) {
  const internal = message.direction === "internal";
  const failed = message.delivery_state === "failed";

  return (
    <li
      data-slot="conversation-message"
      data-direction={message.direction}
      data-delivery={message.delivery_state ?? undefined}
      className={cn(
        "flex max-w-[42rem] flex-col gap-2 rounded-md border p-3",
        TREATMENT[message.direction],
      )}
    >
      <p className="flex flex-wrap items-center gap-2 text-xs text-fg-muted">
        {/* The kind in words. Colour and position alone say nothing to someone
            reading with a screen reader, or to anyone in greyscale. */}
        <span className="font-medium text-fg-default">{t(message.direction)}</span>

        {/*
          `dir="auto"` rather than a forced direction: the browser picks from
          the first strong character, which is the only thing that gets an
          Arabic name inside English chrome — and an English one inside Arabic
          chrome — right without knowing in advance which it is.
        */}
        <bdi dir="auto">{message.author.name}</bdi>

        {message.sent_at !== null && (
          <time dateTime={message.sent_at}>{format.dateTime(message.sent_at)}</time>
        )}

        {message.delivery_state !== null && (
          <span data-slot="delivery-chip">{t(`delivery.${message.delivery_state}`)}</span>
        )}
      </p>

      {internal && (
        /*
         * Said outright, every time, on every note. The one sentence standing
         * between a colleague's private remark and the person it is about.
         */
        <p data-slot="internal-note-badge" className="text-xs font-semibold text-state-warning">
          <InternalBadge />
        </p>
      )}

      {/* Wraps, never clips: a truncated message hides the sentence that
          mattered, and there is no way to reach it. */}
      <p dir="auto" className="whitespace-pre-wrap break-words text-sm text-fg-default">
        {message.body}
      </p>

      {message.attachments.length > 0 && (
        <ul aria-label={t("attachments")} className="flex flex-col gap-1 text-xs">
          {message.attachments.map((attachment) => (
            <li key={attachment.id} className="flex items-center gap-2">
              <bdi dir="auto">{attachment.filename}</bdi>
              <span className="text-fg-muted">{format.fileSize(attachment.byte_size)}</span>
              {attachment.scan_status !== "clean" && (
                <span className="text-fg-muted">{t("scanning")}</span>
              )}
            </li>
          ))}
        </ul>
      )}

      {failed && (
        <div role="alert" className="flex flex-wrap items-center gap-3 text-xs">
          <span className="text-state-danger">{t("failedBody")}</span>

          {/* Two ways out, because a failure has two causes: the pipeline, or
              what was written. Retry addresses one, Edit the other. */}
          <Button variant="secondary" size="sm" onClick={onRetry} disabled={retrying}>
            {t("retry")}
          </Button>
          <Button variant="ghost" size="sm" onClick={onEdit}>
            {t("edit")}
          </Button>
        </div>
      )}
    </li>
  );
}

/** Split out so the exact string is asserted in one place. */
function InternalBadge() {
  const t = useTranslations("ticket.internalNote");

  return <>{t("badge")}</>;
}
