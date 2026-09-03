import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { SESSION_EXPIRED_EVENT } from "@/lib/api/client";
import { request } from "@/lib/api/request";

/**
 * The PUBLISHER side of the session-expiry mechanism.
 *
 * SessionExpiryListener has always been mounted and always had tests — but
 * those tests dispatched the event themselves, so nothing noticed that no
 * production code ever fired it. A lapsed session left the reader on a page
 * whose every request was silently failing. These tests watch the half that
 * was missing: they never dispatch, they only listen.
 */

const heard = vi.fn();

function reply(status: number, body: unknown) {
  vi.stubGlobal(
    "fetch",
    vi.fn(
      async () =>
        new Response(JSON.stringify(body), {
          status,
          headers: {
            "Content-Type": status >= 400 ? "application/problem+json" : "application/json",
          },
        }),
    ),
  );
}

beforeEach(() => {
  heard.mockClear();
  window.addEventListener(SESSION_EXPIRED_EVENT, heard);
});

afterEach(() => {
  window.removeEventListener(SESSION_EXPIRED_EVENT, heard);
  vi.unstubAllGlobals();
});

const call = () => request("/customers").catch(() => undefined);

describe("a 401 from any request announces the session has ended", () => {
  it("fires on the generic platform code", async () => {
    // What Platform (T0) answers for a missing session — which is most 401s.
    reply(401, { status: 401, code: "platform.unauthorized", title: "Authentication required." });

    await call();

    expect(heard).toHaveBeenCalledTimes(1);
  });

  it("fires on the narrowed security code", async () => {
    reply(401, { status: 401, code: "security.session_expired", title: "Session expired." });

    await call();

    expect(heard).toHaveBeenCalledTimes(1);
  });

  it("fires on a 401 with no recognisable body", async () => {
    // An unknown code still means unauthenticated. Treating it as "not really
    // expired" would strand the reader — the exact failure this prevents.
    reply(401, { nothing: "useful" });

    await call();

    expect(heard).toHaveBeenCalledTimes(1);
  });

  it("stays quiet when sign-in is refused", async () => {
    // A wrong password is not a lapsed session. Announcing one here would make
    // the sign-in screen redirect to itself while the reader is typing.
    reply(401, { status: 401, code: "security.invalid_credentials", title: "Invalid." });

    await call();

    expect(heard).not.toHaveBeenCalled();
  });

  it("stays quiet on every other failure", async () => {
    for (const status of [403, 404, 409, 422, 500]) {
      reply(status, { status, code: "platform.forbidden" });
      await call();
    }

    expect(heard).not.toHaveBeenCalled();
  });

  it("stays quiet on success", async () => {
    reply(200, { data: [] });

    await call();

    expect(heard).not.toHaveBeenCalled();
  });

  it("still throws, so the caller can render its own failure", async () => {
    reply(401, { status: 401, code: "platform.unauthorized", detail: "Authentication required." });

    // The redirect is asynchronous; the in-flight screen must not be left
    // believing the request succeeded in the meantime.
    await expect(request("/customers")).rejects.toThrow();
  });
});
