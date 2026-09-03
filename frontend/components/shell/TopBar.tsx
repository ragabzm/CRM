"use client";

import { Menu } from "lucide-react";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";
import { Toast, ToastDescription, ToastProvider, ToastViewport } from "@/components/ui/toast";

import { LanguageToggle } from "./LanguageToggle";
import { NotificationBell } from "./NotificationBell";
import { Sidebar } from "./Sidebar";
import { UserMenu } from "./UserMenu";

/**
 * The global chrome bar.
 *
 * Also hosts the mobile navigation: below `md` the sidebar is a sheet rather
 * than a column, so the same `<Sidebar />` renders in both places and there is
 * only one list of destinations to keep correct.
 */
export function TopBar() {
  const router = useRouter();
  const t = useTranslations("shell");
  const [localeError, setLocaleError] = useState<string | null>(null);

  return (
    <ToastProvider>
      <header className="flex h-14 shrink-0 items-center gap-2 border-b border-border-default bg-surface-base px-4">
        <Sheet>
          <SheetTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              aria-label={t("actions.openNavigation")}
              className="tablet:hidden"
            >
              <Menu aria-hidden="true" />
            </Button>
          </SheetTrigger>
          <SheetContent side="left" className="w-60 p-0">
            <SheetHeader className="sr-only">
              <SheetTitle>{t("nav.label")}</SheetTitle>
              <SheetDescription>{t("nav.label")}</SheetDescription>
            </SheetHeader>
            <Sidebar />
          </SheetContent>
        </Sheet>

        <span className="text-md font-semibold text-fg-default">{t("brand")}</span>

        <div className="flex-1" />

        <LanguageToggle onError={setLocaleError} />
        {/* Clicking a notification opens the ticket it is about. */}
        <NotificationBell onOpenTicket={(id) => router.push(`/tickets/${id}`)} />
        <UserMenu />
      </header>

      {localeError && (
        <Toast tone="danger" open onOpenChange={() => setLocaleError(null)}>
          <ToastDescription>{localeError}</ToastDescription>
        </Toast>
      )}
      <ToastViewport />
    </ToastProvider>
  );
}
