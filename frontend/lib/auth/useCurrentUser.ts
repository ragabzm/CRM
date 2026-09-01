"use client";

export type Role = "agent" | "supervisor" | "administrator";

export interface CurrentUser {
  id: string;
  displayName: string;
  initials: string;
  roles: Role[];
}

/**
 * Roles for the stub user.
 *
 * `NEXT_PUBLIC_STUB_ROLE=agent` forces a non-administrator so the sidebar's
 * Administration guard can be exercised in dev and in tests without real auth.
 * Read via the full `process.env.NEXT_PUBLIC_STUB_ROLE` expression because Next
 * inlines these at build time by literal match.
 */
function stubRoles(): Role[] {
  return process.env.NEXT_PUBLIC_STUB_ROLE === "agent" ? ["agent"] : ["agent", "administrator"];
}

/**
 * The signed-in user.
 *
 * TODO(Story 2.1): replace the fixture with the real session. The shape here is
 * the contract the chrome already codes against — `roles` in particular, since
 * the Administration destination is guarded on it — so that story changes this
 * function's body and nothing else.
 */
export function useCurrentUser(): CurrentUser {
  return {
    id: "01J0000000000000000000USER",
    displayName: "Hana Yousef",
    initials: "HY",
    roles: stubRoles(),
  };
}
