"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { ActionBar } from "@/components/domain/ActionBar/ActionBar";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { ApiError } from "@/lib/api/errors";
import { escalateTicket, reopenTicket, resolveTicket, type Ticket } from "@/lib/api/tickets";

export interface TicketHeaderActionsProps {
  ticket: Ticket;
  /** False when the reader may see the ticket but not change it. */
  editable: boolean;
  onChanged: (ticket: Ticket) => void;
  /** Refetches everything after a conflict, without touching the composer. */
  onReload: () => void;
}

/**
 * Finishing a ticket, where finishing a ticket belongs.
 *
 * There was no action bar at all. Resolving — the single most-used decision on
 * the busiest screen in the product — was a `<select>` labelled "Status" in
 * the property rail, three columns away, indistinguishable in weight from
 * "Category". An agent closing forty tickets a day did it by opening a
 * dropdown forty times.
 *
 * Resolve asks for a note and reopen does not. That asymmetry is deliberate:
 * "why is this finished" is the sentence the next person reads when the
 * customer writes back a week later, and the API requires it. Reopening is
 * usually a reaction to something the customer just said, which is already in
 * the thread.
 */
export function TicketHeaderActions({
  ticket,
  editable,
  onChanged,
  onReload,
}: TicketHeaderActionsProps) {
  const t = useTranslations("ticket.actions");
  const conflictCopy = useTranslations("ticket.conflict");

  const [resolving, setResolving] = useState(false);
  const [escalating, setEscalating] = useState(false);
  const [reason, setReason] = useState("");
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);
  const [conflict, setConflict] = useState(false);
  const [failed, setFailed] = useState(false);

  if (!editable) return null;

  const finished = ticket.status === "resolved" || ticket.status === "closed";

  async function run(work: () => Promise<Ticket>): Promise<void> {
    setBusy(true);
    setConflict(false);
    setFailed(false);

    try {
      onChanged(await work());
      setResolving(false);
      setEscalating(false);
      setNote("");
      setReason("");
    } catch (caught) {
      /*
       * 409 is not a failure — it is somebody else having moved first. It gets
       * its own treatment with a reload, because "try again" on stale state
       * would just fail again.
       */
      if (caught instanceof ApiError && caught.status === 409) setConflict(true);
      else setFailed(true);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex flex-col gap-2" data-slot="ticket-header-actions">
      {conflict && (
        <FormAlert tone="error" action={{ label: conflictCopy("reload"), onSelect: onReload }}>
          {`${conflictCopy("title")} ${conflictCopy("body")}`}
        </FormAlert>
      )}

      {failed && <FormAlert tone="error">{t("failed")}</FormAlert>}

      {/*
        Already escalated: the state and its reason, not another button.
        Escalating twice is allowed by the API — two people who agree have not
        conflicted — but offering the action again invites somebody to press it
        expecting something to happen.
      */}
      {ticket.escalated_at !== null && (
        <p
          className="text-sm font-medium text-state-danger"
          data-slot="escalated-marker"
          data-escalated="true"
        >
          {t("escalatedBanner")}
          {ticket.escalation_reason !== null && (
            <span className="font-normal text-fg-muted"> — {ticket.escalation_reason}</span>
          )}
        </p>
      )}

      {escalating ? (
        <form
          className="flex flex-wrap items-end gap-3"
          onSubmit={(event) => {
            event.preventDefault();

            void run(() => escalateTicket(ticket.id, reason.trim()));
          }}
        >
          <FormField
            label={t("escalationReason")}
            /*
             * Required, and the hint says why. An escalation with no reason
             * reaches a supervisor as an alarm they cannot act on without
             * opening the ticket and working out the problem themselves —
             * which is the work escalating was supposed to save them.
             */
            hint={t("escalationReasonHint")}
            required
            value={reason}
            onChange={(event) => setReason(event.target.value)}
          />

          <SubmitButton pending={busy}>{t("escalate")}</SubmitButton>

          <ActionBar
            actions={[{ id: "cancel", label: t("cancel"), onSelect: () => setEscalating(false) }]}
          />
        </form>
      ) : resolving ? (
        <form
          className="flex flex-wrap items-end gap-3"
          onSubmit={(event) => {
            event.preventDefault();

            void run(() => resolveTicket(ticket.id, ticket.version, note.trim()));
          }}
        >
          <FormField
            label={t("resolutionNote")}
            hint={t("resolutionNoteHint")}
            value={note}
            onChange={(event) => setNote(event.target.value)}
          />

          <SubmitButton pending={busy}>{t("resolve")}</SubmitButton>

          <ActionBar
            actions={[{ id: "cancel", label: t("cancel"), onSelect: () => setResolving(false) }]}
          />
        </form>
      ) : (
        <ActionBar
          actions={
            finished
              ? [
                  {
                    id: "reopen",
                    label: t("reopen"),
                    primary: true,
                    disabled: busy,
                    onSelect: () => void run(() => reopenTicket(ticket.id, ticket.version)),
                  },
                ]
              : [
                  ...(ticket.escalated_at === null
                    ? [
                        {
                          id: "escalate",
                          label: t("escalate"),
                          disabled: busy,
                          onSelect: () => setEscalating(true),
                        },
                      ]
                    : []),
                  {
                    id: "resolve",
                    label: t("resolve"),
                    primary: true,
                    disabled: busy,
                    onSelect: () => setResolving(true),
                  },
                ]
          }
        />
      )}
    </div>
  );
}
