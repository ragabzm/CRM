"use client";

import { useLocale } from "next-intl";
import { useMemo } from "react";

import { DEFAULT_LOCALE, isLocale, type Locale } from "@/lib/i18n/locale";

import {
  formatCurrency,
  formatDate,
  formatDateTime,
  formatFileSize,
  formatNumber,
  formatRelativeTime,
  formatTime,
} from "./index";

/**
 * Locale-bound formatters for client components.
 *
 * Screens call `format.date(value)` rather than `formatDate(value, locale)`, so
 * no call site has to remember to thread the locale through — which is exactly
 * the mistake that produces one component formatting in the wrong language.
 */
export function useFormat() {
  const active = useLocale();

  /*
   * next-intl's locale is the full Intl tag ("ar-u-ca-gregory-nu-latn"), while
   * our formatters key off the short locale. Narrow on the primary subtag.
   */
  const locale: Locale = useMemo(() => {
    const primary = active.split("-")[0];
    return isLocale(primary) ? primary : DEFAULT_LOCALE;
  }, [active]);

  return useMemo(
    () => ({
      locale,
      date: (value: Date | string | number) => formatDate(value, locale),
      dateTime: (value: Date | string | number) => formatDateTime(value, locale),
      time: (value: Date | string | number) => formatTime(value, locale),
      number: (value: number, options?: Intl.NumberFormatOptions) =>
        formatNumber(value, locale, options),
      currency: (value: number, currency: string) => formatCurrency(value, currency, locale),
      relativeTime: (from: Date | string | number, to: Date | string | number) =>
        formatRelativeTime(from, to, locale),
      fileSize: (bytes: number) => formatFileSize(bytes, locale),
    }),
    [locale],
  );
}
