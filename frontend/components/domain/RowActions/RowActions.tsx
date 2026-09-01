"use client";

import { MoreHorizontal } from "lucide-react";
import * as React from "react";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";

export interface RowAction {
  id: string;
  label: string;
  icon?: React.ReactNode;
  onSelect: () => void;
  /** Renders below a separator, for destructive or secondary actions. */
  separated?: boolean;
  destructive?: boolean;
}

export interface RowActionsProps {
  actions: RowAction[];
  /** Names the row in the trigger's accessible name, e.g. "TKT-000123". */
  rowLabel: string;
  className?: string;
}

/**
 * The persistent row overflow control (UX-02).
 *
 * THE RULE: this trigger is always rendered and always visible. It is never
 * gated behind `:hover`, `:focus-within`, `group-hover:` or an opacity
 * transition.
 *
 * Why it matters enough to be a rule: a hover-revealed control does not exist
 * for a touch user, is invisible to anyone scanning the page for what they can
 * do, and forces a keyboard user to tab into a row before they can discover
 * whether the row has actions at all. It also makes the column width shift as
 * the pointer moves down the table.
 *
 * The button occupies its own fixed-width column so the table does not reflow
 * when a menu opens. Enforced by
 * __tests__/components/domain/RowActions.test.tsx, which renders without ever
 * dispatching a hover event and asserts the trigger is present and visible.
 */
export function RowActions({ actions, rowLabel, className }: RowActionsProps) {
  const primary = actions.filter((action) => !action.separated);
  const secondary = actions.filter((action) => action.separated);

  return (
    <div className={cn("flex w-11 items-center justify-center", className)}>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="ghost"
            size="icon"
            data-testid="row-actions-trigger"
            // No hover/focus gating: no `opacity-0`, no `group-hover:opacity-100`.
            className="text-fg-muted hover:text-fg-default"
            aria-label={`Actions for ${rowLabel}`}
          >
            <MoreHorizontal aria-hidden="true" />
          </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" className="min-w-44">
          {primary.map((action) => (
            <DropdownMenuItem key={action.id} onSelect={action.onSelect}>
              {action.icon}
              {action.label}
            </DropdownMenuItem>
          ))}

          {secondary.length > 0 && <DropdownMenuSeparator />}

          {secondary.map((action) => (
            <DropdownMenuItem
              key={action.id}
              onSelect={action.onSelect}
              className={action.destructive ? "text-state-danger" : undefined}
            >
              {action.icon}
              {action.label}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}
