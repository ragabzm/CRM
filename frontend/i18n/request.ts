import { getRequestConfig } from "next-intl/server";

import { INTL_TAG, resolveLocale } from "@/lib/i18n/locale";
import { loadMessages } from "@/lib/i18n/messages";
import { onMissingKey } from "@/lib/i18n/onMissingKey";

/**
 * next-intl's server-side request configuration.
 *
 * Required by next-intl 4 in the App Router: anything rendered on the server —
 * including the server pass of a Client Component — resolves its messages
 * through here rather than through the provider's props.
 *
 * It deliberately calls the same `resolveLocale()` the root layout calls, so
 * the server render and the `<html dir lang>` attributes can never disagree
 * about which language this request is in.
 */
export default getRequestConfig(async () => {
  const locale = await resolveLocale();

  return {
    // The pinned Intl tag, so every server-formatted date and number gets
    // Gregorian and Western digits for the same reason the client does.
    locale: INTL_TAG[locale],
    messages: await loadMessages(locale),
    timeZone: "Asia/Riyadh",
    onError: (error: unknown) => onMissingKey(error, locale),
  };
});
