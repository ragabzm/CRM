"use client";

import { LogOut, User } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { logout } from "@/lib/auth/api";
import { useCurrentUser } from "@/lib/auth/useCurrentUser";

/** Profile and sign-out. */
export function UserMenu() {
  const t = useTranslations("shell.actions");
  const { displayName, initials } = useCurrentUser();

  async function signOut() {
    /*
     * The REAL endpoint. This used to post to `/api/sign-out` — a Next route
     * handler whose whole body was `return 204` under a TODO from Story 2.1.
     * So the menu item returned success, the screen stayed exactly where it
     * was, and the Laravel session was never touched: the person was still
     * signed in, and on a shared machine the next person inherited them.
     *
     * `logout()` invalidates the session server-side and reissues a CSRF
     * token.
     */
    await logout().catch(() => undefined);

    /*
     * A full document replace, not `router.refresh()` or `router.push()`.
     *
     * Two reasons. The client holds the previous person's tickets, counts and
     * notifications in memory and in the RSC cache — a soft navigation keeps
     * all of it. And `replace` rather than `push` means Back does not return
     * to a screen full of somebody else's work.
     *
     * If the request failed, the session is still alive and the gate will send
     * them straight back in. That is the honest outcome: better than a screen
     * that says goodbye while the session is open.
     */
    window.location.replace("/sign-in");
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          aria-label={t("openUserMenu")}
          data-testid="user-menu"
          className="rounded-full"
        >
          {/* Initials rather than an avatar: no image to load, no fallback to
 get wrong, and it reads at any size. */}
          <span aria-hidden="true" className="text-xs font-semibold">
            {initials}
          </span>
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="min-w-52">
        <DropdownMenuLabel>{displayName}</DropdownMenuLabel>
        <DropdownMenuSeparator />

        <DropdownMenuItem asChild>
          <Link href="/profile">
            <User aria-hidden="true" />
            {t("profile")}
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem onSelect={signOut}>
          <LogOut aria-hidden="true" />
          {t("signOut")}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
