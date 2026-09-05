import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { rateByInvitation } from "@/lib/portal/api";

interface Call {
  url: string;
  init: RequestInit;
}

function recordingFetch(body: unknown) {
  const calls: Call[] = [];

  const impl = vi.fn(async (url: string | URL | Request, init: RequestInit = {}) => {
    calls.push({ url: String(url), init });

    return new Response(JSON.stringify(body), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    });
  });

  return { calls, impl: impl as unknown as typeof fetch };
}

const INVITATION = {
  ticket: "01JQZ0000000000000000000AA",
  verdict: "up" as const,
  expires: "1790000000",
  signature: "abc123",
};

beforeEach(() => {
  document.cookie = "XSRF-TOKEN=test-token";
});

afterEach(() => {
  document.cookie = "XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT";
});

/**
 * Sending an answer that arrived by email, where there is no session.
 *
 * Both assertions here are for the same defect, found in a browser and not by
 * a server test: the API routes are Sanctum-stateful, so a same-site POST is
 * CSRF-checked — and somebody who came from an inbox has never had a cookie.
 * The comment box returned 419 while the rating, already recorded by the
 * link's GET, looked fine. The customer wrote a sentence, pressed save, and
 * was told nothing worked.
 */
describe("answering from an emailed invitation", () => {
  it("asks for a CSRF cookie before writing", async () => {
    const { calls, impl } = recordingFetch({ satisfaction: true, satisfaction_comment: null });

    await rateByInvitation(INVITATION, "Quick and clear.", impl);

    expect(calls).toHaveLength(2);
    expect(calls[0]!.url).toContain("/sanctum/csrf-cookie");
    expect(calls[1]!.init.method).toBe("POST");
  });

  it("passes the signature back exactly as it was signed", async () => {
    const { calls, impl } = recordingFetch({ satisfaction: true, satisfaction_comment: null });

    await rateByInvitation(INVITATION, undefined, impl);

    /*
     * `expires` before `signature`, because the server rebuilds the string it
     * hashed from the query in the order it receives it. Swap the two and
     * every answer is refused as forged.
     */
    expect(calls[1]!.url).toContain(
      "/feedback/01JQZ0000000000000000000AA/up?expires=1790000000&signature=abc123",
    );
  });

  it("sends no comment key at all when there are no words", async () => {
    const { calls, impl } = recordingFetch({ satisfaction: true, satisfaction_comment: null });

    await rateByInvitation(INVITATION, undefined, impl);

    // Not an empty string: nothing was written, so nothing should be stored.
    expect(JSON.parse(String(calls[1]!.init.body))).toEqual({});
  });
});
