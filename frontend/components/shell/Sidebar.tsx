"use client";

import { BarChart3, Home, Shield, Ticket, Users } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";

import { useCurrentUser } from "@/lib/auth/useCurrentUser";
import { cn } from "@/lib/utils";

type NavKey = "home" | "tickets" | "customers" | "reports" | "administration";

interface Destination {
  key: NavKey;
  href: string;
  icon: typeof Home;
  /** Rendered only when the user holds one of these roles. */
  requiresRole?: Array<"administrator" | "supervisor">;
}

const DESTINATIONS: Destination[] = [
  { key: "home", href: "/", icon: Home },
  { key: "tickets", href: "/tickets", icon: Ticket },
  { key: "customers", href: "/customers", icon: Users },
  /*
   * Supervisors and above. The figures are about how a DESK is performing
   * rather than about a person, and the question "how did the month go?"
   * belongs to whoever is answerable for the answer — which is also why there
   * is no agent self-view anywhere in the reports.
   */
  {
    key: "reports",
    href: "/reports",
    icon: BarChart3,
    requiresRole: ["supervisor", "administrator"],
  },
  { key: "administration", href: "/admin", icon: Shield, requiresRole: ["administrator"] },
];

/**
 * The five destinations.
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
      !destination.requiresRole ||
      (loaded && destination.requiresRole.some((role) => roles.includes(role))),
  );

  return (
    <nav
      aria-label={t("label")}
      className={cn("flex flex-col gap-2 p-3", className)}
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
              /*
               * `min-h-11` — a real 44px, not a pseudo-element.
               * These stack vertically at `gap-1` (4px), so invisible hit
               * areas would overlap each other and a tap near an edge would
               * land on the wrong destination. Growing the rows for real keeps
               * the 8px separation R-05 asks for alongside the 44px minimum.
               */
              "flex min-h-11 items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors",
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
