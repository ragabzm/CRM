"use client";

import { useRouter } from "next/navigation";

import { useCurrentUser } from "@/lib/auth/useCurrentUser";

import { AgentHomeScreen } from "./AgentHomeScreen";

/** Supplies the home screen with the signed-in identity and the router. */
export function AgentHomePage() {
  const router = useRouter();
  const user = useCurrentUser();

  return (
    <AgentHomeScreen
      // `CurrentUser.id` is a string for display; the ticket filters are typed
      // on the numeric user id the API compares against.
      currentUserId={user.loaded && user.id !== "" ? Number(user.id) : null}
      onOpen={(id) => router.push(`/tickets/${id}`)}
    />
  );
}
