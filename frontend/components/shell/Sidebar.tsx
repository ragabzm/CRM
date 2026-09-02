"use client";

import { Home, Shield, Ticket, Users } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";

import { useCurrentUser } from "@/lib/auth/useCurrentUser";
import { cn } from "@/lib/utils";

type NavKey = "home" | "tickets" | "customers" | "administration";

interface Destination {
  key: NavKey;
  href: string;
  icon: typeof Home;
  /** Rendered only when the user holds this role. */
  requiresRole?: "administrator";
}

const DESTINATIONS: Destination[] = [
  { key: "home", href: "/", icon: Home },
  { key: "tickets", href: "/tickets", icon: Ticket },
  { key: "customers", href: "/customers", icon: Users },
  { key: "administration", href: "/admin", icon: Shield, requiresRole: "administrator" },
];

/**
 * The four destinations.
 *
 * Administration is *absent* for non-administrators rather than present and
 * disabled: unlike the DataTable's locked identity column, there is nothing for
 * the reader to learn here, and advertising a destination they can never reach
 * is an invitation to ask why.
 */
export function Sidebar({ className }: { className?: string }) {
  const t = useTranslations("shell.nav");
  /*
   * usePathname() is typed as string but can be null — outside a router context
   * and during some transitions. An unguarded `.startsWith` there takes down the
   * whole shell, so it degrades to "no destination is current" instead.
   */
  const pathname = usePathname() ?? "";
  const { roles, loaded } = useCurrentUser();

  /*
   * Until the session has been read, a role-gated destination is withheld
   * rather than guessed. Showing it and taking it away a moment later reads as
   * a glitch; withholding it and then adding it reads as loading.
   */
  const visible = DESTINATIONS.filter(
    (destination) =>
      !destination.requiresRole || (loaded && roles.includes(destination.requiresRole)),
  );

  return (
    <nav
      aria-label={t("label")}
      className={cn("flex flex-col gap-1 p-3", className)}
      data-slot="sidebar"
    >
      {visible.map(({ key, href, icon: Icon }) => {
        // Exact match for the root, prefix match elsewhere, so /tickets/42 keeps
        // Tickets marked current.
        const isCurrent = href === "/" ? pathname === "/" : pathname.startsWith(href);

        return (
          <Link
            key={key}
            href={href}
            aria-current={isCurrent ? "page" : undefined}
            className={cn(
              "flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors",
              isCurrent
                ? "bg-accent-subtle font-semibold text-accent-text"
                : "font-medium text-fg-muted hover:bg-surface-hover hover:text-fg-default",
            )}
          >
            <Icon aria-hidden="true" className="size-4 shrink-0" />
            <span>{t(key)}</span>
          </Link>
        );
      })}
    </nav>
  );
}
