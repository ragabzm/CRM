"use client";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export interface BarAction {
  id: string;
  label: string;
  onSelect: () => void;
  /** The one action the screen exists to enable. At most one per bar. */
  primary?: boolean;
  destructive?: boolean;
  disabled?: boolean;
}

export interface ActionBarProps {
  actions: BarAction[];
  className?: string;
}

/**
 * The row of actions at the top of a record or a list.
 *
 * A component rather than loose buttons so the ordering rule holds everywhere:
 * secondary actions first, the primary one last, and anything destructive
 * visually separated and never the default. Assembled by hand, each screen puts
 * Delete somewhere slightly different and eventually one of them puts it where
 * Save was on the previous screen.
 */
export function ActionBar({ actions, className }: ActionBarProps) {
  const ordinary = actions.filter((action) => !action.primary && !action.destructive);
  const destructive = actions.filter((action) => action.destructive);
  const primary = actions.filter((action) => action.primary);

  return (
    <div className={cn("flex flex-wrap items-center gap-2", className)} data-slot="action-bar">
      {[...ordinary, ...destructive, ...primary].map((action) => (
        <Button
          key={action.id}
          variant={action.destructive ? "destructive" : action.primary ? "primary" : "secondary"}
          disabled={action.disabled ?? false}
          onClick={action.onSelect}
        >
          {action.label}
        </Button>
      ))}
    </div>
  );
}
