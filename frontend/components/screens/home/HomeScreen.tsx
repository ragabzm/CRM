"use client";

import { useTranslations } from "next-intl";

/**
 * The Home destination.
 *
 * Deliberately a placeholder: Story 1.3's acceptance criteria are about the
 * shell, not about Home's content. What it does prove is that a screen composes
 * translations rather than literals, and that it reaches Layer B rather than
 * Layer A (enforced by no-restricted-imports).
 */
export function HomeScreen() {
  const t = useTranslations("home");

  return (
    <section className="flex flex-col gap-2">
      <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>
      <p className="max-w-prose text-sm text-fg-muted">{t("placeholder")}</p>
    </section>
  );
}
