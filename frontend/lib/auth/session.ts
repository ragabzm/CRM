import { headers } from "next/headers";
import { redirect } from "next/navigation";

import type { StaffUser } from "./api";

/**
 * The server-side session gate.
 *
 * Until this existed there was NO route protection at all: every protected page
 * rendered its full chrome for an anonymous visitor, and the only thing that
 * ever pushed them out was a client-side listener that nothing dispatched. The
 * API was never at risk — it answers 401 — but a reader saw a working-looking
 * application that could not load a single row.
 *
 * Checked on the server, before anything renders, because the alternative is
 * rendering first and correcting afterwards, which is the flicker this replaces.
 *
 * This module is server-only: importing `next/headers` makes that true by
 * construction rather than by convention.
 */

/**
 * Where the API is reachable FROM THE SERVER.
 *
 * Not the same URL the browser uses. `NEXT_PUBLIC_API_BASE_URL` is deliberately
 * the host-facing address (`localhost:8000`) because that is what a browser on
 * the developer's machine can reach — and inside a container `localhost` is the
 * frontend itself, so a server-side call to it would hit nothing at all. Deploy
 * environments set `API_INTERNAL_BASE_URL` to the service address; the fallback
 * keeps `next dev` on a bare machine working, where both happen to be the same.
 */
export function internalApiBase(): string {
  const internal = process.env.API_INTERNAL_BASE_URL;
  const base = internal ?? process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1";

  return base.replace(/\/$/, "");
}

/**
 * Who is signed in, or null.
 *
 * Asks the API rather than trusting the presence of a cookie: a cookie can be
 * stale, revoked, or belong to a user who has since been deactivated, and
 * `EnsureActiveUser` on the backend is what decides. Reading the cookie's name
 * here would also couple the frontend to the backend's `APP_NAME`.
 */
export async function currentSession(): Promise<StaffUser | null> {
  const incoming = await headers();
  const cookie = incoming.get("cookie");

  // No cookie at all: unauthenticated for certain, and worth not spending a
  // round trip on — every anonymous hit on the site would otherwise reach the
  // API.
  if (cookie === null || cookie === "") return null;

  try {
    const response = await fetch(`${internalApiBase()}/auth/me`, {
      headers: {
        Accept: "application/json",
        cookie,
        /*
         * The origin matters as much as the cookie.
         *
         * Sanctum only loads a session for a request whose Origin is in
         * SANCTUM_STATEFUL_DOMAINS; without one it falls back to token auth,
         * finds no token, and answers 401 — for a perfectly valid session.
         * This gate would then bounce every signed-in person back to sign-in,
         * which sends them to a protected page, which bounces them again.
         *
         * Verified against a running stack: the same cookie returns 200 with an
         * Origin and 401 without one.
         *
         * Taken from the browser's own request rather than configured, because
         * the browser's origin IS the stateful domain by definition — one less
         * environment variable to get wrong in a deployment.
         */
        Origin: browserOrigin(incoming),
      },
      // A session check must never be served from a cache: that is how one
      // reader ends up looking at another reader's identity.
      cache: "no-store",
    });

    if (!response.ok) return null;

    return (await response.json()) as StaffUser;
  } catch {
    /*
     * The API being unreachable is not the same as being signed out, but from
     * here they are indistinguishable, and the safe reading of "I cannot
     * confirm this session" is to not grant it. The sign-in screen will fail
     * loudly against the same outage, which is a clearer place to see it than
     * a half-rendered page.
     */
    return null;
  }
}

/**
 * The origin the browser used to reach this page.
 *
 * `origin` is present on a fetch or a form post but not on a plain document
 * navigation, so the host is the fallback — and behind a proxy the scheme has
 * to come from the forwarded header, since the app itself is reached over
 * plain HTTP.
 */
function browserOrigin(incoming: Headers): string {
  const origin = incoming.get("origin");

  if (origin !== null && origin !== "") return origin;

  const host = incoming.get("host") ?? "localhost:3000";
  const proto = incoming.get("x-forwarded-proto") ?? "http";

  return `${proto}://${host}`;
}

/** The path the reader was trying to reach, published by `middleware.ts`. */
async function attemptedPath(): Promise<string> {
  const header = await headers();

  return header.get("x-pathname") ?? "/";
}

/**
 * Requires a signed-in staff user, or sends the reader to sign-in.
 *
 * The destination travels in `?redirect=` so that a bookmark, a shared link or
 * an expired tab returns to where it was aiming instead of dumping the reader
 * on the home page.
 */
export async function requireSession(): Promise<StaffUser> {
  const user = await currentSession();

  if (user !== null) return user;

  redirect(`/sign-in?redirect=${encodeURIComponent(await attemptedPath())}`);
}
