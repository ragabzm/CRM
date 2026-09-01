"use client";

import { NextIntlClientProvider } from "next-intl";
import type { ReactNode } from "react";

import type { Messages } from "@/lib/i18n/messages";
import { onMissingKey } from "@/lib/i18n/onMissingKey";

export interface IntlProviderProps {
  /** The pinned Intl tag, e.g. "ar-u-ca-gregory-nu-latn". */
  locale: string;
  messages: Messages;
  timeZone: string;
  children: ReactNode;
}

/**
 * Client-side half of the i18n wiring.
 *
 * `onError` is a function, and functions cannot cross the Server → Client
 * boundary: passing it from the root layout fails at render with"Event
 * handlers cannot be passed to Client Component props". So the handler is
 * defined here, in a client module, and the layout passes only serialisable
 * values.
 *
 * The server half of the same contract lives in i18n/request.ts, which wires
 * `onError` for anything rendered on the server. Both call the same
 * `onMissingKey`, so a missing key is reported identically wherever it is hit.
 */
export function IntlProvider({ locale, messages, timeZone, children }: IntlProviderProps) {
  return (
    <NextIntlClientProvider
      locale={locale}
      messages={messages}
      timeZone={timeZone}
      onError={(error) => onMissingKey(error, locale)}
    >
      {children}
    </NextIntlClientProvider>
  );
}
