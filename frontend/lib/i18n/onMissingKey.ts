import type { Locale } from "./locale";

export interface MissingTranslation {
  key: string;
  locale: string;
}

/**
 * Sink for missing translations.
 *
 * A missing key is a content bug, and content bugs are invisible unless someone
 * is told: the string still renders (next-intl falls back to English), so
 * nothing looks broken on screen and nobody files a ticket.
 *
 * This is an object rather than a bare function on purpose. A module-local
 * function call cannot be swapped from outside — ESM bindings are live but the
 * call site is fixed — so neither a test nor Story 1.4 could replace it. A
 * single mutable property is the seam:
 *
 *   translationReporter.report = (r) => postToAdministrators(r);
 *
 * TODO(Story 1.4+): wire the real Administrators channel here.
 */
export const translationReporter: { report: (report: MissingTranslation) => void } = {
  report(report: MissingTranslation): void {
    void report;
  },
};

/** Convenience wrapper over the reporter seam. */
export function logMissingTranslation(report: MissingTranslation): void {
  translationReporter.report(report);
}

/** next-intl's error shape, narrowed to what we need without importing it. */
interface IntlLikeError {
  code?: string;
  originalMessage?: string;
  message?: string;
}

const MISSING_MESSAGE_CODE = "MISSING_MESSAGE";

/**
 * Extracts the key from next-intl's error.
 *
 * The message reads `Could not resolve \`shell.nav.home\` in messages for
 * locale \`ar\`.` — the backticked path is the key. Falling back to the whole
 * message is deliberate: a report naming the wrong key is still better than a
 * silent drop.
 */
export function extractKey(error: unknown): string {
  const message =
    (error as IntlLikeError)?.originalMessage ?? (error as IntlLikeError)?.message ?? "";

  return /`([^`]+)`/.exec(message)?.[1] ?? message;
}

/**
 * Passed to NextIntlClientProvider's `onError`.
 *
 * Only MISSING_MESSAGE is treated as a missing key; other next-intl errors
 * (malformed ICU syntax, bad argument types) are real defects and are re-thrown
 * in development so they surface at the point of the mistake rather than being
 * filed away as a content problem.
 */
export function onMissingKey(error: unknown, locale?: Locale | string): void {
  const code = (error as IntlLikeError)?.code;

  if (code !== undefined && code !== MISSING_MESSAGE_CODE) {
    if (process.env.NODE_ENV === "development") {
      throw error;
    }
    return;
  }

  const report: MissingTranslation = {
    key: extractKey(error),
    locale: locale ?? "unknown",
  };

  if (process.env.NODE_ENV === "development") {
    console.warn(`[i18n] Missing translation: ${report.key} (${report.locale})`);
  }

  translationReporter.report(report);
}
