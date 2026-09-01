import * as React from "react";

import { cn } from "@/lib/utils";

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "h-8 w-full min-w-0 rounded-lg border border-border-default bg-transparent px-2.5 py-1 text-base transition-colors  file:inline-flex file:h-6 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-fg-default placeholder:text-fg-muted disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-surface-base/50 disabled:opacity-50 aria-invalid:border-state-danger aria-invalid:ring-3 aria-invalid:ring-state-danger/20 tablet:text-sm dark:bg-surface-base/30 dark:disabled:bg-surface-base/80 dark:aria-invalid:border-state-danger/50 dark:aria-invalid:ring-state-danger/40",
        className,
      )}
      {...props}
    />
  );
}

export { Input };
