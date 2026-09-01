"use client";

import { Languages } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useTransition } from "react";

import { Button } from "@/components/ui/button";
import { DEFAULT_LOCALE, isLocale, type Locale } from "@/lib/i18n/locale";

export interface LanguageToggleProps {
  /** Surfaced when the preference cannot be saved. */
  onError?: (message: string) => void;
}

/**
 * EN ⇄ AR.
 *
 * The label is the *other* locale's endonym — "العربية"while English is
 * active — because a control named after the current state reads as a status
 * rather than an action.
 *
 * The preference is written server-side and the tree is refreshed, rather than
 * flipped in client state: `dir` and `lang` live on <html> in the root layout,
 * so the server is the only thing that can change them coherently.
 */
export function LanguageToggle({ onError }: LanguageToggleProps) {
  const t = useTranslations("shell.actions");
  const errors = useTranslations("errors");
  const router = useRouter();
  const [isPending, startTransition] = useTransition();

  const active = useLocale();
  const primary = active.split("-")[0];
  const current: Locale = isLocale(primary) ? primary : DEFAULT_LOCALE;
  const next: Locale = current === "ar" ? "en" : "ar";

  async function switchLocale() {
    try {
      const response = await fetch("/api/locale", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ locale: next }),
      });

      if (!response.ok) throw new Error(`locale switch failed: ${response.status}`);

      startTransition(() => router.refresh());
    } catch {
      // Nothing changed, so say so rather than leaving a control that looks
      // like it worked.
      onError?.(errors("localeSwitchFailed"));
    }
  }

  return (
    <Button
      variant="ghost"
      size="md"
      disabled={isPending}
      onClick={switchLocale}
      data-testid="language-toggle"
      data-next-locale={next}
      icon={<Languages aria-hidden="true" />}
    >
      {t("toggleLanguage")}
    </Button>
  );
}
