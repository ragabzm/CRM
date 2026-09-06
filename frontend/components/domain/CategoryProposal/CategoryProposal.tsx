"use client";

import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";

import { AiLabel } from "@/components/domain/AiLabel/AiLabel";
import { assistCategory, type CategoryProposal as Proposal } from "@/lib/api/assist";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface CategoryProposalProps {
  ticketId: string;
  /** The category the ticket already has, if any. */
  current: number | null;
  /** Confirming runs the ordinary category change, with this person's name on it. */
  onConfirm: (categoryId: number) => void;
}

/**
 * A category the machine thinks this is, BESIDE the field.
 *
 * Never inside it. A pre-filled field is an application: the agent sees a
 * value, assumes somebody chose it, and saves. Beside the field it is what it
 * actually is — a suggestion with a button that says confirm, and the ticket
 * unchanged until somebody presses it.
 *
 * Confirming goes through the ordinary Story 4.1 command path, carrying the
 * version and attributed to the person who pressed it — never to System and
 * never to the AI. The history has to say who decided, and the answer is
 * always a person.
 *
 * BECAUSE NOTHING IS APPLIED AUTOMATICALLY, there is no confidence anywhere:
 * no slider, no score, no "apply above N". A threshold only exists to decide
 * when to skip the human, and the human is never skipped.
 */
export function CategoryProposal({ ticketId, current, onConfirm }: CategoryProposalProps) {
  const t = useTranslations("ticket.assist");

  const [proposal, setProposal] = useState<Proposal | null>(null);

  useEffect(() => {
    let cancelled = false;

    void assistCategory(ticketId)
      .then((value) => {
        if (!cancelled) setProposal(value);
      })
      .catch(() => {
        /*
         * Silent. The capability being off, transmission disabled and the
         * provider being down all look the same from here, and all three mean
         * the same thing on screen: nothing.
         */
      });

    return () => {
      cancelled = true;
    };
  }, [ticketId]);

  if (proposal === null || proposal.category_id === current) {
    /*
     * Nothing to say when the ticket already has the category being proposed.
     * A suggestion to change something to what it already is reads as the
     * product not knowing what it is looking at.
     */
    return null;
  }

  return (
    <AiLabel className="mt-1">
      <p className="flex flex-wrap items-center gap-2 text-xs">
        <span className="text-fg-muted">{t("proposed")}</span>
        <span className="text-fg-default" dir="auto" data-slot="proposed-category">
          {proposal.name}
        </span>

        <button
          type="button"
          onClick={() => onConfirm(proposal.category_id)}
          className={cn("underline text-fg-default", TOUCH_TARGET)}
          data-slot="confirm-category"
        >
          {t("confirmCategory")}
        </button>
      </p>
    </AiLabel>
  );
}
