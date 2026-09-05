"use client";

import { useRouter, useSearchParams } from "next/navigation";

import { useCurrentUser } from "@/lib/auth/useCurrentUser";

import { AgentHomeScreen, HOME_TABS, type HomeTab } from "./AgentHomeScreen";

/** Supplies the home screen with the signed-in identity and the router. */
export function AgentHomePage() {
  const router = useRouter();
  const user = useCurrentUser();
  const params = useSearchParams();

  /*
   * `?tab=tasks` is where a reminder on a standalone task lands.
   *
   * A query parameter rather than a route: tasks are a tab on Home, not a
   * destination, and giving them `/tasks` would be the first half of the
   * sidebar entry this decision rules out. Validated against the known set so
   * a stale or hand-edited link falls back to the queue rather than rendering
   * an empty panel.
   */
  const asked = params.get("tab");
  const tab = HOME_TABS.includes(asked as HomeTab) ? (asked as HomeTab) : undefined;

  return (
    <AgentHomeScreen
      // `CurrentUser.id` is a string for display; the ticket filters are typed
      // on the numeric user id the API compares against.
      currentUserId={user.loaded && user.id !== "" ? Number(user.id) : null}
      {...(tab === undefined ? {} : { initialTab: tab })}
      onOpen={(id) => router.push(`/tickets/${id}`)}
    />
  );
}
