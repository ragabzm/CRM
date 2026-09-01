import { NextResponse } from "next/server";

import { LOCALE_COOKIE, LOCALE_COOKIE_MAX_AGE, isLocale } from "@/lib/i18n/locale";

/**
 * Persists the language preference.
 *
 * A cookie rather than a URL segment because the choice is a property of the
 * user, not of the page: every route should render in their language without
 * carrying a locale prefix, and a shared link should open in the reader's
 * language rather than the sender's.
 */
export async function POST(request: Request): Promise<NextResponse> {
  const body: unknown = await request.json().catch(() => null);

  const locale =
    typeof body === "object" && body !== null
      ? (body as Record<string, unknown>).locale
      : undefined;

  if (typeof locale !== "string" || !isLocale(locale)) {
    return NextResponse.json({ error: "invalid_locale" }, { status: 400 });
  }

  const response = NextResponse.json({ ok: true, locale });

  response.cookies.set(LOCALE_COOKIE, locale, {
    // Readable by the client so the toggle can reflect state before the refresh
    // lands. It is a display preference, not a credential.
    httpOnly: false,
    sameSite: "lax",
    maxAge: LOCALE_COOKIE_MAX_AGE,
    path: "/",
  });

  return response;
}
