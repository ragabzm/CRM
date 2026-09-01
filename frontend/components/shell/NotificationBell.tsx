"use client";

import { Bell } from "lucide-react";
import { useTranslations } from "next-intl";

import { BidiValue } from "@/components/domain/BidiValue/BidiValue";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

export interface NotificationItem {
  id: string;
  /** Already-translated text. Notifications come from the server, not messages/. */
  text: string;
  /** A ticket reference or similar LTR run, isolated when rendered. */
  reference?: string;
}

export interface NotificationBellProps {
  items?: NotificationItem[];
}

/**
 * The notification bell.
 *
 * Story 1.3 scope is deliberately small: a plain list and an empty state. No
 * unread badge, no pagination, no read/unread state — those need real data and
 * a real notification centre, and a fake badge would be a number the product
 * cannot honour.
 */
export function NotificationBell({ items = [] }: NotificationBellProps) {
  const t = useTranslations("shell");

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          aria-label={t("actions.openNotifications")}
          data-testid="notification-bell"
        >
          <Bell aria-hidden="true" />
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-80">
        <DropdownMenuLabel>{t("notifications.heading")}</DropdownMenuLabel>
        <DropdownMenuSeparator />

        {items.length === 0 ? (
          <p className="px-2 py-6 text-center text-sm text-fg-muted">{t("notifications.empty")}</p>
        ) : (
          <ul className="flex flex-col gap-1 p-1" data-testid="notification-list">
            {items.map((item) => (
              <li key={item.id} className="rounded-md px-2 py-2 text-sm text-fg-default">
                <span>{item.text}</span>
                {item.reference && (
                  <>
                    {" "}
                    <BidiValue className="text-fg-muted">{item.reference}</BidiValue>
                  </>
                )}
              </li>
            ))}
          </ul>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
