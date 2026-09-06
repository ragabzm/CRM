"use client";

import { useTranslations } from "next-intl";
import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

export interface AiLabelProps {
  /** The generated artefact this label belongs to. */
  children: ReactNode;
  className?: string;
}

/**
 * Wraps anything a model produced, and says so.
 *
 * ONE component, and no capability invents its own labelling. That is not
 * tidiness: five capabilities each writing their own caption is five chances
 * for one of them to be quieter than the others, and the quiet one is where a
 * suggestion gets mistaken for a fact.
 *
 * The label says two things and both are load-bearing. That it came from AI —
 * so the reader knows who to believe. And that it needs checking — because the
 * rule this whole epic rests on is that AI proposes and a PERSON decides, and
 * an artefact presented without that reads as a decision already taken.
 *
 * It is text, not a colour and not an icon. A tinted panel says nothing in
 * greyscale, nothing to a screen reader, and nothing at all on a printed
 * ticket — and this is the one caption that must survive all three.
 */
export function AiLabel({ children, className }: AiLabelProps) {
  const t = useTranslations("ai");

  return (
    <section
      data-slot="ai-label"
      /*
       * A labelled region, so the caption is announced with the content rather
       * than read as a stray sentence above it.
       */
      aria-label={t("label")}
      className={cn("flex flex-col gap-1", className)}
    >
      <p className="text-xs text-fg-muted" data-slot="ai-label-text">
        {t("label")}
      </p>

      {children}
    </section>
  );
}
