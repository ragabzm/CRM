import type { ReactNode } from "react";

import { Sidebar } from "./Sidebar";
import { TopBar } from "./TopBar";

/**
 * The frame every screen renders inside.
 *
 * Direction is handled entirely by CSS logical properties and grid flow: the
 * sidebar is column 1 in both writing modes, which the browser places on the
 * left in LTR and on the right in RTL without a second stylesheet and without
 * any `dir` attribute below <html>. That is the whole point of the one-switch
 * rule — there is no mirroring code here to get wrong.
 */
export function AppShell({ children }: { children: ReactNode }) {
  return (
    <div className="min-h-screen tablet:grid tablet:grid-cols-[15rem_1fr]" data-slot="app-shell">
      {/* Below md the sidebar lives in TopBar's sheet instead. */}
      <aside
        data-slot="app-shell-sidebar"
        className="hidden border-e border-border-default bg-surface-base tablet:block"
      >
        <Sidebar />
      </aside>

      <div className="flex min-h-screen flex-col">
        <TopBar />
        <main className="flex-1 p-6">{children}</main>
      </div>
    </div>
  );
}
