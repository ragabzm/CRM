"use client";

import { ChevronDown, ChevronUp, Pencil, Trash2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { useRef, useState } from "react";

import { BidiValue } from "@/components/domain/BidiValue/BidiValue";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { Button } from "@/components/ui/button";
import type { QuickReply } from "@/lib/api/admin";

export interface QuickReplyListProps {
  replies: QuickReply[];
  /** Receives the COMPLETE new order of ids. */
  onReorder: (order: string[]) => void;
  onEdit: (reply: QuickReply) => void;
  onDelete: (reply: QuickReply) => void;
  /** Renders the "add" affordance. Omit it for a read-only list. */
  onAdd?: () => void;
}

/**
 * The ordered list, reordered from the keyboard.
 *
 * Buttons, not drag-and-drop. A drag handle is unreachable without a pointer
 * and unusable with a tremor or a trackpad, and the accessible fallback people
 * bolt onto a drag library afterwards is always the same pair of buttons — so
 * they are the primary mechanism here rather than the consolation prize.
 *
 * Each move announces the new position through a live region, because "it
 * moved" is invisible to a reader who cannot see the list jump, and focus stays
 * on the button that was pressed so a second press keeps moving the same row.
 */
export function QuickReplyList({
  replies,
  onReorder,
  onEdit,
  onDelete,
  onAdd,
}: QuickReplyListProps) {
  const t = useTranslations("admin.quickReply");
  const [announcement, setAnnouncement] = useState("");
  const listRef = useRef<HTMLUListElement | null>(null);

  function move(index: number, delta: number) {
    const target = index + delta;
    if (target < 0 || target >= replies.length) return;

    const next = [...replies];
    const [moved] = next.splice(index, 1);
    next.splice(target, 0, moved!);

    setAnnouncement(
      t("movedTo", {
        label: moved!.label.en,
        position: target + 1,
        total: next.length,
      }),
    );

    onReorder(next.map((reply) => reply.id));

    /*
     * Keep focus on the same control after the list re-renders in its new
     * order. Without this, focus falls back to <body> and a keyboard user has
     * to tab all the way back in to press "move up" a second time.
     */
    queueMicrotask(() => {
      const selector = `[data-reply-id="${moved!.id}"] [data-move="${delta < 0 ? "up" : "down"}"]`;
      listRef.current?.querySelector<HTMLElement>(selector)?.focus();
    });
  }

  const addButton = onAdd ? (
    <Button className="w-fit" onClick={onAdd}>
      {t("add")}
    </Button>
  ) : null;

  if (replies.length === 0) {
    return (
      <div className="flex flex-col items-center gap-4">
        <EmptyState headline={t("empty")} description={t("emptyBody")} />
        {addButton}
      </div>
    );
  }

  return (
    <>
      {/* Polite: a reorder is the reader's own action, not an interruption. */}
      <p role="status" aria-live="polite" className="sr-only">
        {announcement}
      </p>

      <ul ref={listRef} aria-label={t("listLabel")} className="flex flex-col gap-2">
        {replies.map((reply, index) => (
          <li
            key={reply.id}
            data-reply-id={reply.id}
            className="flex items-start gap-3 rounded-md border border-border-default bg-surface-base p-3"
          >
            <div className="flex flex-col">
              <Button
                variant="ghost"
                size="icon"
                data-move="up"
                disabled={index === 0}
                aria-label={`${t("moveUp")}: ${reply.label.en}`}
                onClick={() => move(index, -1)}
              >
                <ChevronUp aria-hidden="true" className="size-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                data-move="down"
                disabled={index === replies.length - 1}
                aria-label={`${t("moveDown")}: ${reply.label.en}`}
                onClick={() => move(index, 1)}
              >
                <ChevronDown aria-hidden="true" className="size-4" />
              </Button>
            </div>

            <div className="flex min-w-0 flex-1 flex-col gap-1">
              <p className="text-sm font-medium text-fg-default">
                <BidiValue as="span">{reply.label.en}</BidiValue>
                <span className="px-2 text-fg-subtle" aria-hidden="true">
                  ·
                </span>
                <span dir="rtl">{reply.label.ar}</span>
              </p>
              {/* Isolated: an English preview inside Arabic prose reorders
                  without it, and only in Arabic. */}
              <p className="line-clamp-2 text-xs text-fg-muted">
                <BidiValue>{reply.body.en}</BidiValue>
              </p>
            </div>

            <div className="flex items-center gap-1">
              <Button
                variant="ghost"
                size="icon"
                aria-label={`${t("editAction")}: ${reply.label.en}`}
                onClick={() => onEdit(reply)}
              >
                <Pencil aria-hidden="true" className="size-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                aria-label={`${t("delete")}: ${reply.label.en}`}
                onClick={() => onDelete(reply)}
              >
                <Trash2 aria-hidden="true" className="size-4 text-state-danger" />
              </Button>
            </div>
          </li>
        ))}
      </ul>

      {addButton}
    </>
  );
}
