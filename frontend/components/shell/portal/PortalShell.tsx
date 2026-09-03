"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useTranslations } from "next-intl";
import type { ReactNode } from "react";

import { LanguageToggle } from "@/components/shell/LanguageToggle";
import { cn } from "@/lib/utils";

export interface PortalShellProps {
  children: ReactNode;
  /** Hidden before sign-in: there is nothing yet to navigate between. */
  signedIn?: boolean;
}

/**
 * The customer's frame. Deliberately not the staff one.
 *
 * A slim top bar and THREE destinations — no sidebar, no queue, no counts. A
 * customer is not working a list; they came to ask one question or check on one
 * they already asked, usually on a phone, often once. The staff shell is built
 * for somebody who lives in it all day, and giving that to a customer says the
 * product is not for them.
 *
 * It shares the design system, the locale and the formatting layer, and nothing
 * else — which is what stops a change made for agents from quietly reshaping
 * the customer's experience.
 */
export function PortalShell({ children, signedIn = true }: PortalShellProps) {
  const t = useTranslations("portal");
  const pathname = usePathname();

  const destinations = [
    { href: "/portal/requests", label: t("nav.requests") },
    { href: "/portal/requests/new", label: t("nav.new") },
    { href: "/portal/account", label: t("nav.account") },
  ];

  return (
    <div className="flex min-h-screen flex-col bg-surface-app" data-slot="portal-shell">
      <header className="border-b border-border-default bg-surface-base">
        {/*
          Mobile-first: the bar is a single row at every width. A layout that
          only works once it has a sidebar's worth of space is a layout most
          customers never see working.
        */}
        <div className="mx-auto flex w-full max-w-3xl items-center gap-3 px-4 py-3">
          <Link href="/portal/requests" className="font-semibold text-fg-default">
            {t("brand")}
          </Link>

          <div className="ms-auto">
            <LanguageToggle />
          </div>
        </div>

        {signedIn && (
          <nav
            aria-label={t("brand")}
            data-slot="portal-nav"
            className="mx-auto flex w-full max-w-3xl gap-1 overflow-x-auto px-4 pb-2"
          >
            {destinations.map((destination) => {
              const current = pathname?.startsWith(destination.href) ?? false;

              return (
                <Link
                  key={destination.href}
                  href={destination.href}
                  aria-current={current ? "page" : undefined}
                  className={cn(
                    "whitespace-nowrap rounded-md px-3 py-1.5 text-sm transition-colors",
                    current
                      ? "bg-accent-subtle font-semibold text-accent-text"
                      : "font-medium text-fg-muted hover:bg-surface-hover hover:text-fg-default",
                  )}
                >
                  {destination.label}
                </Link>
              );
            })}
          </nav>
        )}
      </header>

      <main className="mx-auto w-full max-w-3xl flex-1 p-4">{children}</main>
    </div>
  );
}
