"use client";

import { useTranslations } from "next-intl";

import { BidiValue } from "@/components/domain/BidiValue/BidiValue";
import { Button } from "@/components/ui/button";
import type { DuplicateMatch } from "@/lib/api/customers";

export interface DuplicateOfferProps {
  matches: DuplicateMatch[];
  onOpenExisting: (match: DuplicateMatch) => void;
  onCreateAnyway: () => void;
  busy?: boolean;
}

/**
 * "This person may already exist."
 *
 * An OFFER, not a refusal. Two people in one household genuinely share a
 * landline, so a form that refuses the second is one an agent has to work
 * around while a real person waits on the line. What this catches is the
 * accidental case — the same customer entered twice because nobody searched
 * first — and the fix for that is showing who already exists at the moment of
 * entry.
 *
 * Both routes out are available and neither is disabled. "Open existing" is
 * listed first because it is the right answer most of the time, but "Create
 * anyway" is a plain button, not something the agent has to argue with.
 */
export function DuplicateOffer({
  matches,
  onOpenExisting,
  onCreateAnyway,
  busy = false,
}: DuplicateOfferProps) {
  const t = useTranslations("customers.duplicates");

  if (matches.length === 0) return null;

  return (
    <section
      role="alert"
      data-slot="duplicate-offer"
      className="flex flex-col gap-3 rounded-md border border-state-warning-border bg-state-warning-bg p-4"
    >
      <div className="flex flex-col gap-1">
        <h3 className="text-sm font-semibold text-fg-default">{t("title")}</h3>
        <p className="text-sm text-fg-muted">{t("body", { count: matches.length })}</p>
      </div>

      <ul className="flex flex-col gap-2">
        {matches.map((match) => (
          <li
            key={`${match.customer_id}:${match.matched_value}`}
            className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border-default bg-surface-base px-3 py-2"
          >
            <div className="flex min-w-0 flex-col">
              <span className="text-sm font-medium text-fg-default">
                {match.full_name}
                {match.state === "inactive" && (
                  // Someone returning after two years is exactly the duplicate
                  // worth catching, and their old record holds the history.
                  <span className="ms-2 rounded-full border border-border-default px-2 py-0.5 text-xs text-fg-muted">
                    {t("inactive")}
                  </span>
                )}
              </span>
              <span className="text-xs text-fg-muted">
                {t("matchedOn", { value: "" })}
                <BidiValue>{match.matched_value}</BidiValue>
              </span>
            </div>

            <Button variant="secondary" onClick={() => onOpenExisting(match)}>
              {t("openExisting")}
            </Button>
          </li>
        ))}
      </ul>

      <div className="flex flex-col gap-1">
        <Button className="w-fit" onClick={onCreateAnyway} disabled={busy}>
          {t("createAnyway")}
        </Button>
        <p className="text-xs text-fg-muted">{t("createAnywayHint")}</p>
      </div>
    </section>
  );
}
