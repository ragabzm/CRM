import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

// `vi.mock` factories are hoisted above every const in the module, so the
// shared state they close over has to be created inside `vi.hoisted`.
const { headerStore, redirect } = vi.hoisted(() => ({
  headerStore: { cookie: null as string | null, origin: null as string | null },
  redirect: vi.fn((to: string) => {
    // `redirect()` throws in Next too — it is how it unwinds the render.
    throw new Error(`REDIRECT:${to}`);
  }),
}));

vi.mock("next/headers", () => ({
  headers: async () => ({
    get: (name: string) =>
      name === "cookie"
        ? headerStore.cookie
        : name === "x-pathname"
          ? "/customers"
          : name === "origin"
            ? headerStore.origin
            : name === "host"
              ? "crm.example.test"
              : null,
  }),
}));

vi.mock("next/navigation", () => ({ redirect }));

import { currentSession, internalApiBase, requireSession } from "@/lib/auth/session";

const USER = { id: 1, name: "Dana", email: "d@x.test", preferred_locale: "en", roles: ["agent"] };

function api(status: number, body: unknown = {}) {
  vi.stubGlobal(
    "fetch",
    vi.fn(
      async () =>
        new Response(JSON.stringify(body), {
          status,
          headers: { "Content-Type": "application/json" },
        }),
    ),
  );
}

beforeEach(() => {
  headerStore.cookie = "ragab-crm-api-session=abc";
  headerStore.origin = null;
  redirect.mockClear();
});

afterEach(() => vi.unstubAllGlobals());

describe("the server-side session gate", () => {
  it("returns the signed-in user", async () => {
    api(200, USER);

    await expect(currentSession()).resolves.toEqual(USER);
  });

  it("treats a 401 as signed out", async () => {
    api(401, { code: "platform.unauthorized" });

    await expect(currentSession()).resolves.toBeNull();
  });

  it("does not spend a round trip when there is no cookie", async () => {
    headerStore.cookie = null;
    api(200, USER);

    await expect(currentSession()).resolves.toBeNull();
    expect(globalThis.fetch).not.toHaveBeenCalled();
  });

  it("forwards the caller's cookie, since the API decides", async () => {
    api(200, USER);

    await currentSession();

    const init = vi.mocked(globalThis.fetch).mock.calls[0]![1]!;

    expect((init.headers as Record<string, string>).cookie).toBe("ragab-crm-api-session=abc");
  });

  it("never caches the answer", async () => {
    api(200, USER);

    await currentSession();

    // A cached session check is how one reader is shown another reader's
    // identity.
    expect(vi.mocked(globalThis.fetch).mock.calls[0]![1]!.cache).toBe("no-store");
  });

  it("treats an unreachable API as not-signed-in", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => {
        throw new Error("ECONNREFUSED");
      }),
    );

    // Cannot confirm is not the same as confirmed, and the safe reading of an
    // unconfirmable session is to not grant it.
    await expect(currentSession()).resolves.toBeNull();
  });

  it("sends a signed-out reader to sign-in, carrying where they aimed", async () => {
    api(401, {});

    await expect(requireSession()).rejects.toThrow("REDIRECT:/sign-in?redirect=%2Fcustomers");
  });

  it("lets a signed-in reader through without redirecting", async () => {
    api(200, USER);

    await expect(requireSession()).resolves.toEqual(USER);
    expect(redirect).not.toHaveBeenCalled();
  });
});

describe("the internal API address", () => {
  const original = { ...process.env };

  afterEach(() => {
    process.env.API_INTERNAL_BASE_URL = original.API_INTERNAL_BASE_URL;
    process.env.NEXT_PUBLIC_API_BASE_URL = original.NEXT_PUBLIC_API_BASE_URL;
  });

  it("prefers the internal address over the browser-facing one", () => {
    process.env.API_INTERNAL_BASE_URL = "http://backend-web:8000/api/v1";
    process.env.NEXT_PUBLIC_API_BASE_URL = "http://localhost:8000/api/v1";

    // Inside a container `localhost` is the frontend itself, so using the
    // public address server-side reaches nothing at all.
    expect(internalApiBase()).toBe("http://backend-web:8000/api/v1");
  });

  it("falls back to the public address when both are the same machine", () => {
    delete process.env.API_INTERNAL_BASE_URL;
    process.env.NEXT_PUBLIC_API_BASE_URL = "http://localhost:8000/api/v1";

    expect(internalApiBase()).toBe("http://localhost:8000/api/v1");
  });
});

describe("the request the gate makes", () => {
  const USER_ROW = { id: 1, name: "Dana", email: "d@x.test", preferred_locale: "en", roles: [] };

  beforeEach(() => {
    headerStore.cookie = "ragab-crm-api-session=abc";
    vi.stubGlobal(
      "fetch",
      vi.fn(
        async () =>
          new Response(JSON.stringify(USER_ROW), {
            status: 200,
            headers: { "Content-Type": "application/json" },
          }),
      ),
    );
  });

  it("presents the browser's origin, not just the cookie", async () => {
    await currentSession();

    const init = vi.mocked(globalThis.fetch).mock.calls[0]![1]!;
    const sent = init.headers as Record<string, string>;

    /*
     * Sanctum only loads a session for a request whose Origin is a stateful
     * domain. Without one it falls back to token auth, finds no token, and
     * answers 401 for a perfectly valid session — and this gate then bounces
     * every signed-in person back to sign-in, which sends them to a protected
     * page, which bounces them again.
     *
     * Caught by running the stack, not by a test: the same cookie returned 200
     * with an Origin and 401 without one.
     */
    expect(sent.Origin).toBe("http://crm.example.test");
  });
});
