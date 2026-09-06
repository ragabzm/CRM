"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { ApiError } from "@/lib/api/errors";
import type { ChatConversationRow } from "@/lib/api/chat";
import { useFormat } from "@/lib/format/useFormat";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface ChatDeskProps {
  waiting: ChatConversationRow[];
  mine: ChatConversationRow[];
  onTake: (id: string) => Promise<void>;
  onOpenTicket: (ticketId: string) => void;
}

/**
 * Who is waiting, and what this agent is already in the middle of.
 *
 * A LIST, not a queue. There is no position, no priority, no routing and
 * nothing that hands a conversation to anybody: agents look at who is waiting
 * and one of them clicks. Every mechanism that would decide for them needs to
 * know who is available, and availability is the state this story rules out —
 * so the first routing rule would drag presence in behind it.
 *
 * Taking one can FAIL, and that is normal rather than exceptional: two people
 * watching the same list will click the same row within the same second. The
 * refusal names the colleague who got there first, because "could not take"
 * would send somebody back to a list where the row is already gone.
 */
export function ChatDesk({ waiting, mine, onTake, onOpenTicket }: ChatDeskProps) {
  const t = useTranslations("home.chat");
  const format = useFormat();

  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function take(id: string) {
    setBusy(id);
    setError(null);

    try {
      await onTake(id);
    } catch (caught) {
      /*
       * The server's own words. "A colleague got there first — Nadia Salem is
       * already answering this conversation" is something an agent can act on;
       * a generic failure is not.
       */
      setError(caught instanceof ApiError ? (caught.problem?.detail ?? t("failed")) : t("failed"));
    } finally {
      setBusy(null);
    }
  }

  function row(conversation: ChatConversationRow, action: "take" | "open") {
    return (
      <li
        key={conversation.id}
        data-slot="chat-row"
        data-state={conversation.state}
        className="flex flex-wrap items-center gap-3 py-3"
      >
        <div className="flex min-w-0 flex-1 flex-col gap-0.5">
          <span className="text-sm text-fg-default" dir="auto">
            {conversation.subject ?? t("noSubject")}
          </span>

          <span className="flex flex-wrap items-center gap-2 text-xs text-fg-muted">
            <span dir="auto">{conversation.visitor_name ?? t("anonymous")}</span>

            {conversation.reference !== null && <span>{conversation.reference}</span>}

            {conversation.waiting_since !== null && (
              /*
                How long they have been there, because that is the only thing
                on this row that decides who to take next — and it is the
                agent's decision, not a routing rule's.
              */
              <span>{format.dateTime(new Date(conversation.waiting_since))}</span>
            )}
          </span>
        </div>

        {action === "take" ? (
          <button
            type="button"
            disabled={busy === conversation.id}
            onClick={() => void take(conversation.id)}
            className={cn(
              "rounded-md border border-border-default px-3 py-1.5 text-sm text-fg-default",
              TOUCH_TARGET,
            )}
            data-slot="chat-take"
          >
            {t("take")}
          </button>
        ) : (
          conversation.ticket_id !== null && (
            <button
              type="button"
              onClick={() => onOpenTicket(conversation.ticket_id as string)}
              className={cn("text-sm underline text-fg-default", TOUCH_TARGET)}
              data-slot="chat-open"
            >
              {t("open")}
            </button>
          )
        )}
      </li>
    );
  }

  return (
    <div className="flex flex-col gap-4" data-slot="chat-desk">
      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      <section className="flex flex-col gap-1">
        <h3 className="text-sm font-semibold text-fg-default">{t("waiting")}</h3>

        {waiting.length === 0 ? (
          <EmptyState headline={t("nobodyWaiting")} description={t("nobodyWaitingBody")} />
        ) : (
          <ul className="flex flex-col divide-y divide-border-subtle">
            {waiting.map((conversation) => row(conversation, "take"))}
          </ul>
        )}
      </section>

      <section className="flex flex-col gap-1">
        <h3 className="text-sm font-semibold text-fg-default">{t("mine")}</h3>

        {mine.length === 0 ? (
          <p className="text-xs text-fg-muted">{t("noneTaken")}</p>
        ) : (
          <ul className="flex flex-col divide-y divide-border-subtle">
            {mine.map((conversation) => row(conversation, "open"))}
          </ul>
        )}
      </section>

      {/*
        The trade, stated rather than hidden. A chat pane that quietly lagged
        would be read as broken instead of as a decision, and an agent would
        report a bug that is the design.
      */}
      <p className="text-xs text-fg-subtle">{t("pollNote")}</p>
    </div>
  );
}
