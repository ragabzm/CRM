import { render as rtlRender, type RenderOptions, type RenderResult } from "@testing-library/react";
import { NextIntlClientProvider } from "next-intl";
import type { ReactElement, ReactNode } from "react";

import { INTL_TAG, type Locale } from "@/lib/i18n/locale";
import ar from "@/messages/ar.json";
import en from "@/messages/en.json";

const MESSAGES = { en, ar } as const;

/**
 * Wraps a tree in the same provider the root layout mounts.
 *
 * Components that render user-facing text call `useTranslations`, which throws
 * without a provider — so tests exercise real message lookup rather than a stub,
 * and a missing key fails the test instead of silently rendering the key path.
 */
export function withIntl(children: ReactNode, locale: Locale = "en") {
  return (
    <NextIntlClientProvider
      locale={INTL_TAG[locale]}
      messages={MESSAGES[locale]}
      timeZone="Asia/Riyadh"
    >
      {children}
    </NextIntlClientProvider>
  );
}

/**
 * Drop-in replacement for Testing Library's `render` that supplies the provider.
 *
 * Import this instead of `@testing-library/react` in any test that renders a
 * component carrying translated text.
 */
export function render(
  ui: ReactElement,
  options: RenderOptions & { locale?: Locale } = {},
): RenderResult {
  const { locale = "en", ...rest } = options;

  return rtlRender(withIntl(ui, locale), rest);
}

export { act, cleanup, fireEvent, screen, waitFor, within } from "@testing-library/react";
export { ar, en };
