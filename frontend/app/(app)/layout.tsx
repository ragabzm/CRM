import type { ReactNode } from "react";

import { AppShell } from "@/components/shell/AppShell";
import { requireSession } from "@/lib/auth/session";

/**
 * Everything behind sign-in, and the ONE place the chrome is mounted.
 *
 * Both jobs belong together. The shell used to live in the root layout, which
 * wraps the sign-in routes too — so a signed-out visitor was shown a sidebar
 * full of destinations they could not reach, which `(auth)/layout.tsx` has
 * always said it did not want. And because screens could not tell whether they
 * were already inside chrome, several of them wrapped themselves in `AppShell`
 * as well, rendering a second sidebar and top bar INSIDE the first on
 * `/customers` and `/admin/*`.
 *
 * One mount, one gate, one place to look. `AppShellSingleMountTest` fails if a
 * second mount ever comes back.
 */
export default async function AppLayout({ children }: { children: ReactNode }) {
  // Before anything renders. A page that draws first and corrects afterwards
  // shows the reader a working application for as long as the round trip takes.
  await requireSession();

  return <AppShell>{children}</AppShell>;
}
