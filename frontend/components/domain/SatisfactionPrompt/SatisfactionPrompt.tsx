"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { ApiError } from "@/lib/api/errors";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface SatisfactionPromptProps {
  /** Null means nobody has answered — not neutral, and not zero. */
  satisfaction: boolean | null;
  satisfactionComment: string | null;
  /** False once the change window has closed. The server decides this. */
  canRate: boolean;
  onRate: (positive: boolean, comment?: string) => Promise<void>;
}

/**
 * Did it go well? Two answers, and no third.
 *
 * No stars, no five-point scale, no faces and no number anywhere. Two is not a
 * simplification of five — it is the whole design: a boolean never needs a
 * midpoint, never needs converting when somebody changes the scale, and never
 * turns "compare this quarter to last" into an argument about normalisation.
 *
 * Both controls carry a WORD. Not colour alone, which is invisible in
 * greyscale and to anybody who cannot distinguish the two; not an icon alone,
 * which a screen reader announces as nothing. The thumb is decoration beside
 * the label, marked `aria-hidden` so it is not read out twice.
 *
 * The comment comes AFTER the rating and never before it, and is never
 * required. A rating alone is a complete answer — asking for words first is
 * how a one-tap question becomes a form somebody abandons.
 */
export function SatisfactionPrompt({
  satisfaction,
  satisfactionComment,
  canRate,
  onRate,
}: SatisfactionPromptProps) {
  const t = useTranslations("portal.feedback");

  const [comment, setComment] = useState(satisfactionComment ?? "");
  const [commenting, setCommenting] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const answered = satisfaction !== null;

  async function answer(positive: boolean, withComment?: string) {
    setBusy(true);
    setError(null);

    try {
      await onRate(positive, withComment);
      setCommenting(withComment === undefined);
    } catch (caught) {
      /*
       * The server's own words. "Your answer was recorded and the time to
       * change it has passed" tells somebody exactly what happened; a generic
       * failure leaves them tapping again.
       */
      setError(caught instanceof ApiError ? (caught.problem?.detail ?? t("failed")) : t("failed"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="flex flex-col gap-3" data-slot="satisfaction-prompt">
      <h2 className="text-sm font-semibold text-fg-default">
        {answered ? t("answeredTitle") : t("title")}
      </h2>

      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      {!canRate && answered && (
        /*
         * Locked, and it SAYS so. A control that silently ignores a tap makes
         * somebody tap again and then conclude the product is broken — and
         * they would be right.
         */
        <p className="text-xs text-fg-muted" data-slot="rating-locked">
          {t("locked")}
        </p>
      )}

      <div className="flex flex-wrap gap-2" role="group" aria-label={t("title")}>
        {[
          { value: true, label: t("good"), mark: "👍" },
          { value: false, label: t("bad"), mark: "👎" },
        ].map((choice) => {
          const chosen = satisfaction === choice.value;

          return (
            <button
              key={String(choice.value)}
              type="button"
              disabled={busy || !canRate}
              aria-pressed={chosen}
              onClick={() => void answer(choice.value)}
              data-slot="satisfaction-choice"
              data-choice={choice.value ? "good" : "bad"}
              className={cn(
                /*
                 * A border and a weight change carry the chosen state, not a
                 * colour — UX-03 requires it to survive greyscale, and
                 * `aria-pressed` carries it for anybody not looking at all.
                 */
                "flex items-center gap-2 rounded-md border px-3 py-2 text-sm",
                chosen
                  ? "border-fg-default font-semibold text-fg-default"
                  : "border-border-default text-fg-muted",
                TOUCH_TARGET,
              )}
            >
              {/* Decoration. The label is what is announced and what is read. */}
              <span aria-hidden="true">{choice.mark}</span>
              {choice.label}
            </button>
          );
        })}
      </div>

      {answered && canRate && !commenting && (
        <button
          type="button"
          onClick={() => setCommenting(true)}
          className={cn("self-start text-xs underline text-fg-muted", TOUCH_TARGET)}
          data-slot="add-comment"
        >
          {satisfactionComment === null ? t("addComment") : t("editComment")}
        </button>
      )}

      {answered && canRate && commenting && (
        <form
          className="flex flex-col gap-2"
          onSubmit={(event) => {
            event.preventDefault();
            void answer(satisfaction, comment);
          }}
        >
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-fg-muted">{t("commentLabel")}</span>
            <textarea
              rows={3}
              dir="auto"
              value={comment}
              onChange={(event) => setComment(event.target.value)}
              className="rounded-md border border-border-default bg-surface-default p-2 text-sm"
            />
          </label>

          <div>
            <SubmitButton pending={busy}>{t("saveComment")}</SubmitButton>
          </div>
        </form>
      )}

      {answered && !canRate && satisfactionComment !== null && (
        <p className="text-sm text-fg-default" dir="auto">
          {satisfactionComment}
        </p>
      )}
    </section>
  );
}
