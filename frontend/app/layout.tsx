import type { Metadata } from "next";
import { IntlProvider } from "@/components/i18n/IntlProvider";
import { SessionExpiryListener } from "@/components/auth/SessionExpiryListener";
import { TooltipProvider } from "@/components/ui/tooltip";
import { INTL_TAG, directionFor, resolveLocale } from "@/lib/i18n/locale";
import { loadMessages } from "@/lib/i18n/messages";

import { plexSans, plexSansArabic } from "./fonts";
import "./globals.css";

export const metadata: Metadata = {
  title: "Ragab CRM",
  description: "Ragab CRM",
};

/**
 * The bilingual shell.
 *
 * `dir` and `lang` are set on <html> from the active locale, which is what makes
 * every logical utility (ms-/me-/ps-/pe-/start-/end-) resolve correctly. Nothing
 * below this point may use a physical utility — ESLint enforces it. This is the
 * ONLY place the direction is decided; there is no second Arabic stylesheet and
 * no per-component `dir` override.
 *
 * Fonts are self-hosted via next/font/local. `shadcn init` adds a
 * next/font/google loader here; it has been removed and must not come back —
 * the intake forbids an external font CDN in the request path.
 */
export default async function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  const locale = await resolveLocale();
  const dir = directionFor(locale);
  const messages = await loadMessages(locale);

  return (
    <html
      // `lang` stays the bare BCP-47 base tag: assistive technology expects
      // "ar", not the "-u-ca-gregory-nu-latn" extension.
      lang={locale}
      dir={dir}
      className={`${plexSans.variable} ${plexSansArabic.variable}`}
    >
      <body className="min-h-screen bg-surface-app font-sans text-fg-default antialiased">
        <IntlProvider
          // The full extension tag goes here, because this is what every
          // client-side Intl formatter next-intl builds will receive. Pinning it
          // once is what keeps Arabic on Gregorian dates and Western digits.
          locale={INTL_TAG[locale]}
          messages={messages}
          timeZone="Asia/Riyadh"
        >
          <TooltipProvider>
            {/* Redirects a lapsed session to sign-in WITHOUT clearing storage —
                see the note in SessionExpiryListener about composer drafts. */}
            <SessionExpiryListener />
            {/* No chrome here. It is mounted once, in `(app)/layout.tsx`, so
                the sign-in routes render bare and no screen has to know
                whether it is already inside a shell. */}
            {children}
          </TooltipProvider>
        </IntlProvider>
      </body>
    </html>
  );
}
