"use client";

import { useEffect, useState } from "react";

import { me, type StaffUser } from "./api";

export type Role = "agent" | "supervisor" | "administrator" | "customer";

export interface CurrentUser {
  id: string;
  displayName: string;
  initials: string;
  roles: Role[];
  /** False until the session has been read, so nothing is guessed meanwhile. */
  loaded: boolean;
}

const ROLES: readonly string[] = ["agent", "supervisor", "administrator", "customer"];

function initialsOf(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "";

  const first = parts[0]![0] ?? "";
  const last = parts.length > 1 ? (parts[parts.length - 1]![0] ?? "") : "";

  return (first + last).toUpperCase();
}

/** The signed-in user, read from the session the browser already holds. */
export function useCurrentUser(): CurrentUser {
  const [user, setUser] = useState<StaffUser | null>(null);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    let cancelled = false;

    me()
      .then((profile) => {
        if (!cancelled) setUser(profile);
      })
      .catch(() => undefined)
      .finally(() => {
        if (!cancelled) setLoaded(true);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const name = user?.name ?? "";

  return {
    id: user ? String(user.id) : "",
    displayName: name,
    initials: initialsOf(name),
    /*
     * Narrowed against the known set rather than cast. This drives what the
     * chrome OFFERS, never what it permits — every endpoint re-checks the
     * capability server-side, so a session claiming a role it does not hold
     * gains a menu item and nothing else.
     */
    roles: (user?.roles ?? []).filter((role): role is Role => ROLES.includes(role)),
    loaded,
  };
}
