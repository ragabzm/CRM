"use client";

import { Bell } from "lucide-react";
import { useTranslations } from "next-intl";
import { useCallback, useState } from "react";

import { BidiValue } from "@/components/domain/BidiValue/BidiValue";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { listNotifications, markNotificationRead, type NotificationRow } from "@/lib/api/tickets";
import { useFreshQuery } from "@/lib/data/useFreshQuery";
import { cn } from "@/lib/utils";

export interface NotificationBellProps {
  /** Supplied in tests and stories; the bell fetches for itself otherwise. */
  items?: NotificationRow[];
  unreadCount?: number;
  onOpenTicket?: (ticketId: string) => void;
}

/** How often the bell catches up while somebody is on a page. */
const REFETCH_MS = 60_000;

/**
 * The notification bell.
 *
 * ONE list, read and unread mingled, newest first. Somebody checking the bell
 * wants to know what happened; splitting that into "new" and "everything"
 * makes them look in two places for one answer.
 *
 * The badge counts the UNREAD, which is not the length of the list — the list
 * is capped at twenty, and a badge reading "20" when there were ninety would be
 * worse than no badge because it would look precise.
 *
 * Every item carries its ticket, because a notification you cannot act on just
 * makes somebody go and find the ticket by hand.
 */
export function NotificationBell({ items, unreadCount, onOpenTicket }: NotificationBellProps) {
  const t = useTranslations("shell");

  const fetcher = useCallback(() => listNotifications(), []);

  // Skipped entirely when the caller supplied data, so a test or a story does
  // not have to stub a request it never wanted.
  const query = useFreshQuery(
    "notifications",
    fetcher,
    items === undefined ? { refetchInterval: REFETCH_MS } : {},
  );

  const [readLocally, setReadLocally] = useState<string[]>([]);

  const rows = items ?? query.data?.data ?? [];
  const unread = (unreadCount ?? query.data?.unread_count ?? 0) - readLocally.length;

  async function open(item: NotificationRow) {
    if (!item.read && !readLocally.includes(item.id)) {
      /*
       * Marked read optimistically. The badge dropping the instant somebody
       * looks is the behaviour they expect; waiting for a round trip makes the
       * bell feel broken, and a failure here costs nothing — the next refetch
       * puts the count back.
       */
      setReadLocally((current) => [...current, item.id]);
      void markNotificationRead(item.id).catch(() => undefined);
    }

    if (item.ticket_id !== null) onOpenTicket?.(item.ticket_id);
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          aria-label={
            unread > 0
              ? `${t("actions.openNotifications")} — ${t("notifications.unread", { count: unread })}`
              : t("actions.openNotifications")
          }
          data-testid="notification-bell"
          className="relative"
        >
          <Bell aria-hidden="true" />

          {unread > 0 && (
            /*
             * The count is in the button's accessible name above, so this is
             * decorative: a screen reader hearing "3" floating next to "Open
             * notifications" learns nothing about what the 3 counts.
             */
            <span
              aria-hidden="true"
              data-slot="unread-badge"
              className="absolute -top-0.5 -end-0.5 min-w-4 rounded-full bg-state-danger px-1 text-[10px] font-semibold leading-4 text-white"
            >
              {unread > 9 ? "9+" : unread}
            </span>
          )}
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-80">
        <DropdownMenuLabel>{t("notifications.heading")}</DropdownMenuLabel>
        <DropdownMenuSeparator />

        {rows.length === 0 ? (
          <p className="px-2 py-6 text-center text-sm text-fg-muted">{t("notifications.empty")}</p>
        ) : (
          <ul className="flex flex-col gap-1 p-1" data-testid="notification-list">
            {rows.map((item) => {
              const read = item.read || readLocally.includes(item.id);

              return (
                <li key={item.id} data-read={read ? "true" : "false"}>
                  <button
                    type="button"
                    onClick={() => void open(item)}
                    className={cn(
                      "w-full rounded-md px-2 py-2 text-start text-sm hover:bg-surface-hover",
                      // Weight, not colour alone: unread has to survive
                      // greyscale, and this is a list scanned at a glance.
                      read ? "text-fg-muted" : "font-medium text-fg-default",
                    )}
                  >
                    {/* Server-written, in the reader's own language. */}
                    <span dir="auto">{item.text}</span>

                    {item.reference !== null && (
                      <>
                        {" "}
                        <BidiValue className="text-fg-muted">{item.reference}</BidiValue>
                      </>
                    )}
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
