import type { Locale } from "./locale";

export type Messages = typeof import("../../messages/en.json");

/**
 * Loads the message catalogue for a locale.
 *
 * English is imported as the type source so a key added to ar.json but not to
 * en.json is a type error rather than a runtime surprise; the parity test covers
 * the other direction.
 */
export async function loadMessages(locale: Locale): Promise<Messages> {
  return locale === "ar"
    ? ((await import("../../messages/ar.json")).default as Messages)
    : ((await import("../../messages/en.json")).default as Messages);
}
