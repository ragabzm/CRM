import type { Metadata } from "next";

import { TooltipProvider } from "@/components/ui/tooltip";
import { directionFor, resolveLocale } from "@/lib/i18n/locale";

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
 * below this point may use a physical utility — ESLint enforces it.
 *
 * Fonts are self-hosted via next/font/local. `shadcn init` adds a
 * next/font/google loader here; it has been removed and must not come back —
 * the intake forbids an external font CDN in the request path.
 */
export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const locale = resolveLocale();
  const dir = directionFor(locale);

  return (
    <html
      lang={locale}
      dir={dir}
      className={`${plexSans.variable} ${plexSansArabic.variable}`}
    >
      <body className="min-h-screen bg-surface-app font-sans text-fg-default antialiased">
        <TooltipProvider>{children}</TooltipProvider>
      </body>
    </html>
  );
}
