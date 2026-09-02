import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import {
  AuthError,
  changePassword,
  forgotPassword,
  getCsrf,
  login,
  logout,
  me,
  resetPassword,
  updateProfile,
} from "@/lib/auth/api";

interface Call {
  url: string;
  init: RequestInit;
}

function recordingFetch(responses: Array<{ status: number; body?: unknown }>) {
  const calls: Call[] = [];
  let index = 0;

  const impl = vi.fn(async (url: string | URL | Request, init: RequestInit = {}) => {
    calls.push({ url: String(url), init });

    const next = responses[Math.min(index, responses.length - 1)]!;
    index++;

    return new Response(next.body === undefined ? null : JSON.stringify(next.body), {
      status: next.status,
      headers: { "Content-Type": "application/json" },
    });
  });

  return { calls, impl: impl as unknown as typeof fetch };
}

const USER = { id: 1, name: "Hana", email: "h@ragab.test", preferred_locale: "en", roles: [] };

beforeEach(() => {
  document.cookie = "XSRF-TOKEN=test-token";
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe("cookie mode carries no credential in JavaScript", () => {
  it("sends credentials on every call", async () => {
    const { calls, impl } = recordingFetch([{ status: 204 }, { status: 200, body: USER }]);

    await login({ email: "h@ragab.test", password: "Correct-Horse-9" }, impl);

    // Without this the session cookie is never sent and never stored.
    for (const call of calls) {
      expect(call.init.credentials).toBe("include");
    }
  });

  it("primes the CSRF cookie before signing in", async () => {
    const { calls, impl } = recordingFetch([{ status: 204 }, { status: 200, body: USER }]);

    await login({ email: "h@ragab.test", password: "Correct-Horse-9" }, impl);

    expect(calls[0]!.url).toContain("/sanctum/csrf-cookie");
    expect(calls[1]!.url).toContain("/api/v1/auth/login");
  });

  it("echoes the XSRF cookie in the header Laravel expects", async () => {
    const { calls, impl } = recordingFetch([{ status: 204 }, { status: 200, body: USER }]);

    await login({ email: "h@ragab.test", password: "Correct-Horse-9" }, impl);

    const headers = calls[1]!.init.headers as Record<string, string>;
    expect(headers["X-XSRF-TOKEN"]).toBe("test-token");
  });

  it("returns no token from sign-in", async () => {
    const { impl } = recordingFetch([{ status: 204 }, { status: 200, body: USER }]);

    const user = await login({ email: "h@ragab.test", password: "Correct-Horse-9" }, impl);

    expect(JSON.stringify(user)).not.toMatch(/token/i);
  });

  it("writes nothing to web storage", async () => {
    const { impl } = recordingFetch([{ status: 204 }, { status: 200, body: USER }]);
    const localSet = vi.spyOn(Storage.prototype, "setItem");

    await login({ email: "h@ragab.test", password: "Correct-Horse-9" }, impl);
    await me(impl);

    // "No credential, token or refresh value is ever written to localStorage or
    // sessionStorage" — the session is an http-only cookie the browser owns.
    expect(localSet).not.toHaveBeenCalled();
  });
});

describe("failures surface as typed problems", () => {
  it("throws AuthError carrying the problem document", async () => {
    const problem = {
      type: "https://errors.ragab-crm/security.invalid_credentials",
      title: "Sign-in failed.",
      status: 401,
      detail: "These credentials do not match our records.",
      instance: "/api/v1/auth/login",
      code: "security.invalid_credentials",
      trace_id: "01HZY",
    };
    const { impl } = recordingFetch([{ status: 204 }, { status: 401, body: problem }]);

    await expect(login({ email: "a@b.test", password: "x" }, impl)).rejects.toBeInstanceOf(
      AuthError,
    );

    await expect(login({ email: "a@b.test", password: "x" }, impl)).rejects.toMatchObject({
      status: 401,
      problem: { code: "security.invalid_credentials" },
    });
  });

  it("surfaces the rate-limit status", async () => {
    const { impl } = recordingFetch([
      { status: 204 },
      {
        status: 429,
        body: {
          code: "platform.too_many_requests",
          status: 429,
          title: "x",
          type: "y",
          instance: "z",
          trace_id: "t",
        },
      },
    ]);

    await expect(login({ email: "a@b.test", password: "x" }, impl)).rejects.toMatchObject({
      status: 429,
    });
  });
});

describe("the auth surface", () => {
  it("signs out with a POST", async () => {
    const { calls, impl } = recordingFetch([{ status: 200, body: { status: "ok" } }]);

    await logout(impl);

    const target = calls.find((call) => call.url.includes("/api/v1/auth/logout"))!;
    expect(target.init.method).toBe("POST");
  });

  it("requests a reset link with a POST", async () => {
    const { calls, impl } = recordingFetch([
      { status: 204 },
      { status: 202, body: { status: "accepted" } },
    ]);

    await forgotPassword({ email: "a@b.test" }, impl);

    const target = calls.find((call) => call.url.includes("/auth/password/forgot"))!;
    expect(target.init.method).toBe("POST");
    expect(JSON.parse(target.init.body as string)).toEqual({ email: "a@b.test" });
  });

  it("changes a password with a POST", async () => {
    const { calls, impl } = recordingFetch([
      { status: 204 },
      { status: 200, body: { status: "ok" } },
    ]);

    await changePassword(
      {
        current_password: "Old-Passw0rd-Long",
        password: "New-Passw0rd-Long",
        password_confirmation: "New-Passw0rd-Long",
      },
      impl,
    );

    const target = calls.find((call) => call.url.includes("/api/v1/profile/password"))!;
    expect(target.init.method).toBe("POST");
  });

  it("patches the profile", async () => {
    const { calls, impl } = recordingFetch([{ status: 204 }, { status: 200, body: USER }]);

    await updateProfile({ name: "New", preferred_locale: "ar" }, impl);

    const target = calls.find((call) => call.url.includes("/api/v1/profile"))!;
    expect(target.init.method).toBe("PATCH");
    expect(JSON.parse(target.init.body as string)).toEqual({ name: "New", preferred_locale: "ar" });
  });

  it("resets a password with the token from the link", async () => {
    const { calls, impl } = recordingFetch([
      { status: 204 },
      { status: 200, body: { status: "ok" } },
    ]);

    await resetPassword(
      {
        token: "tok",
        email: "a@b.test",
        password: "New-Passw0rd-Long",
        password_confirmation: "New-Passw0rd-Long",
      },
      impl,
    );

    const target = calls.find((call) => call.url.includes("/auth/password/reset"))!;
    expect(JSON.parse(target.init.body as string).token).toBe("tok");
  });

  it("getCsrf hits Sanctum's cookie endpoint", async () => {
    const { calls, impl } = recordingFetch([{ status: 204 }]);

    await getCsrf(impl);

    expect(calls[0]!.url).toContain("/sanctum/csrf-cookie");
    expect(calls[0]!.init.credentials).toBe("include");
  });
});
