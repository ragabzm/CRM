"use client";

import { LogOut, User } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter } from "next/navigation";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useCurrentUser } from "@/lib/auth/useCurrentUser";

/** Profile and sign-out. */
export function UserMenu() {
  const t = useTranslations("shell.actions");
  const { displayName, initials } = useCurrentUser();
  const router = useRouter();

  async function signOut() {
    await fetch("/api/sign-out", { method: "POST" }).catch(() => undefined);
    router.refresh();
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
