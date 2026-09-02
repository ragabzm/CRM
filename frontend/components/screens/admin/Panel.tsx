import type { ReactNode } from "react";

/** A titled panel with an explanation of what it configures. */
export function Panel({
  title,
  hint,
  children,
}: {
  title: string;
  hint?: string;
  children: ReactNode;
}) {
  return (
    <section className="flex flex-col gap-4 rounded-lg border border-border-default bg-surface-base p-5">
      <div className="flex flex-col gap-1">
        <h2 className="text-base font-semibold text-fg-default">{title}</h2>
        {hint && <p className="text-sm text-fg-muted">{hint}</p>}
      </div>
      {children}
    </section>
  );
}
