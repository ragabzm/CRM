"use client";

import { useTranslations } from "next-intl";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { Button } from "@/components/ui/button";

export interface StaleVersionBannerProps {
  onReload: () => void;
  busy?: boolean;
}

/**
 * "Someone else changed this while you were editing."
 *
 * One sentence and one button. The alternative — a diff of what changed with
 * per-field merge choices — asks someone to adjudicate a conflict they have no
 * context for, while a customer waits. Reloading and redoing the edit takes
 * seconds and is always correct.
 *
 * Rendered by any surface that catches TicketStaleVersionError, so the wording
 * is the same wherever the race happens.
 */
export function StaleVersionBanner({ onReload, busy = false }: StaleVersionBannerProps) {
  const t = useTranslations("tickets.staleVersion");

  return (
    <FormAlert tone="error">
      <span className="flex flex-wrap items-center gap-3">
        <span>
          {t("message")} {t("detail")}
        </span>
        <Button variant="secondary" onClick={onReload} disabled={busy}>
          {t("reload")}
        </Button>
      </span>
    </FormAlert>
  );
}
