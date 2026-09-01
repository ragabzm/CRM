import { describe, expect, it } from "vitest";

import { POST } from "@/app/api/locale/route";
import { LOCALE_COOKIE, LOCALE_COOKIE_MAX_AGE } from "@/lib/i18n/locale";

function postJson(body: unknown) {
  return new Request("http://localhost/api/locale", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
}

describe("POST /api/locale", () => {
  it.each(["en", "ar"])("persists %s and returns 200", async (locale) => {
    const response = await POST(postJson({ locale }));

    expect(response.status).toBe(200);

    const cookie = response.cookies.get(LOCALE_COOKIE);
    expect(cookie?.value).toBe(locale);
  });

  it("sets a year-long, lax, path-wide cookie", async () => {
    const response = await POST(postJson({ locale: "ar" }));
    const cookie = response.cookies.get(LOCALE_COOKIE);

    // A language choice is a setting, not a session.
    expect(cookie?.maxAge).toBe(LOCALE_COOKIE_MAX_AGE);
    expect(cookie?.sameSite).toBe("lax");
    expect(cookie?.path).toBe("/");
    // Readable by the client so the toggle can reflect state before refresh;
    // it is a display preference, not a credential.
    expect(cookie?.httpOnly).toBe(false);
  });

  it.each([{ locale: "fr" }, { locale: "" }, { locale: 42 }, {}, null])(
    "rejects %s with 400 and sets no cookie",
    async (body) => {
      const response = await POST(postJson(body));

      expect(response.status).toBe(400);
      expect(response.cookies.get(LOCALE_COOKIE)).toBeUndefined();
    },
  );

  it("rejects a malformed body rather than throwing", async () => {
    const request = new Request("http://localhost/api/locale", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: "{ not json",
    });

    const response = await POST(request);

    expect(response.status).toBe(400);
  });
});
