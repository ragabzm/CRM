"use client";

import { useTranslations } from "next-intl";

import type { ReportFigure } from "@/lib/api/reports";
import { useFormat } from "@/lib/format/useFormat";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface ReportCardsProps {
  cards: ReportFigure[];
  onOpen: (filters: Record<string, string | number>) => void;
}

/**
 * The six figures, each of which opens the tickets behind it.
 *
 * Every card is a BUTTON, not a tile with a number on it. A figure nobody can
 * click into is a figure nobody can check, and a figure nobody can check is
 * one that gets quoted in a meeting and then quietly disbelieved. That is the
 * whole reason this surface exists rather than a spreadsheet.
 *
 * Tabular numerals, so six figures in a row line up at the decimal instead of
 * shuffling as the digits change — and Western digits in both locales, which
 * the format layer already guarantees for everything on this screen.
 */
export function ReportCards({ cards, onOpen }: ReportCardsProps) {
  const t = useTranslations("reports.cards");
  const format = useFormat();

  return (
    <ul className="grid grid-cols-2 gap-3 tablet:grid-cols-3" data-slot="report-cards">
      {cards.map((card) => (
        <li key={card.key}>
          <button
            type="button"
            onClick={() => onOpen(card.filters)}
            data-slot="report-card"
            data-card={card.key}
            className={cn(
              "flex w-full flex-col items-start gap-1 rounded-md border border-border-default p-3 text-start",
              TOUCH_TARGET,
            )}
          >
            <span className="num text-xl font-semibold tabular-nums text-fg-default">
              {format.number(card.value)}
            </span>

            <span className="text-xs text-fg-muted">{t(card.key)}</span>
          </button>
        </li>
      ))}
    </ul>
  );
}
