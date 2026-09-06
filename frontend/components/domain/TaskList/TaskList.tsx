"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { ApiError } from "@/lib/api/errors";
import type { PersonalTask } from "@/lib/api/personal";
import { useFormat } from "@/lib/format/useFormat";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface TaskListProps {
  tasks: PersonalTask[];
  onToggle: (id: string, completed: boolean) => Promise<void>;
  /** Opens the ticket a task is attached to. Standalone tasks open nothing. */
  onOpenTicket: (ticketId: string) => void;
}

/**
 * An agent's own list, and the reason each row is on it.
 *
 * An OVERDUE task stays where it is and says so. It is never auto-dismissed
 * and never quietly slides down, because the whole value of writing something
 * down is that it is still there when you forgot it. Three cues carry that,
 * and colour is the fourth rather than the first: the word "Overdue", the due
 * date itself, and a heavier row — so the list still reads with every colour
 * removed.
 *
 * A task attached to a ticket carries its reference and subject, because
 * "Chase the courier hub" tells an agent nothing about which courier.
 */
export function TaskList({ tasks, onToggle, onOpenTicket }: TaskListProps) {
  const t = useTranslations("home.tasks");
  const format = useFormat();

  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function toggle(task: PersonalTask) {
    setBusy(task.id);
    setError(null);

    try {
      await onToggle(task.id, task.completed_at === null);
    } catch (caught) {
      setError(caught instanceof ApiError ? (caught.problem?.detail ?? t("failed")) : t("failed"));
    } finally {
      setBusy(null);
    }
  }

  if (tasks.length === 0) {
    return <EmptyState headline={t("empty")} description={t("emptyBody")} />;
  }

  return (
    <div className="flex flex-col gap-2" data-slot="task-list">
      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      <ul className="flex flex-col divide-y divide-border-subtle">
        {tasks.map((task) => {
          const done = task.completed_at !== null;

          return (
            <li
              key={task.id}
              data-slot="task-row"
              data-overdue={task.overdue}
              data-complete={done}
              className="flex flex-wrap items-start gap-3 py-3"
            >
              <input
                type="checkbox"
                checked={done}
                disabled={busy === task.id}
                onChange={() => void toggle(task)}
                aria-label={t("toggle", { title: task.title })}
                className={cn("mt-0.5 size-4 shrink-0", TOUCH_TARGET)}
              />

              <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span
                  className={cn(
                    "text-sm text-fg-default",
                    // Struck through AND lighter. A tick alone is easy to miss
                    // on a long list somebody is scanning.
                    done && "line-through text-fg-muted",
                    task.overdue && "font-semibold",
                  )}
                  dir="auto"
                >
                  {task.title}
                </span>

                <span className="flex flex-wrap items-center gap-1 text-xs text-fg-muted">
                  {task.ticket_id === null ? (
                    // Said out loud, so an unattached task does not read as one
                    // whose ticket failed to load.
                    <span>{t("standalone")}</span>
                  ) : (
                    <button
                      type="button"
                      onClick={() => onOpenTicket(task.ticket_id as string)}
                      className={cn("underline", TOUCH_TARGET)}
                      data-slot="task-ticket"
                    >
                      {task.ticket_reference ?? t("standalone")}
                    </button>
                  )}

                  {task.ticket_subject !== null && (
                    <span className="truncate" dir="auto">
                      {task.ticket_subject}
                    </span>
                  )}
                </span>
              </div>

              {task.due_at !== null && (
                <span
                  className={cn(
                    "shrink-0 text-xs",
                    task.overdue ? "font-semibold text-fg-default" : "text-fg-muted",
                  )}
                  data-slot="task-due"
                >
                  {/*
                    The WORD, then the date. "Overdue" survives greyscale, a
                    screen reader and a printed page; a red tint survives none
                    of them.
                  */}
                  {task.overdue
                    ? `${t("overdue")} · ${format.dateTime(new Date(task.due_at))}`
                    : format.dateTime(new Date(task.due_at))}
                </span>
              )}
            </li>
          );
        })}
      </ul>
    </div>
  );
}
