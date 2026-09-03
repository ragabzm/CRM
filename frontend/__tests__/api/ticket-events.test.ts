import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { listTicketEvents } from "@/lib/api/tickets";

/**
 * The loader Story 4.4's history panel will call.
 *
 * No UI in this story — but the request shape is a contract, and getting the
 * cursor wrong is the kind of thing that only shows up as a panel that repeats
 * rows forever.
 */

const PAGE = {
  data: [
    {
      id: "01E1",
      kind: "ticket.status_changed",
      actor: { type: "staff", id: "7", display_name: "Dana" },
      before: { status: "open" },
      after: { status: "pending" },
      meta: null,
      version_after: 2,
      occurred_at: "2026-09-02T09:15:00Z",
    },
  ],
  next_cursor: "Y3Vyc29y",
  has_more: true,
};

let urls: string[] = [];

beforeEach(() => {
  urls = [];

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      urls.push(String(input));

      return new Response(JSON.stringify(PAGE), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

describe("listTicketEvents", () => {
  it("returns the page as the API shaped it", async () => {
    await expect(listTicketEvents("01T1")).resolves.toEqual(PAGE);
  });

  it("asks for no cursor on the first page", async () => {
    await listTicketEvents("01T1");

    expect(urls[0]).toContain("/tickets/01T1/events");
    expect(urls[0]).not.toContain("cursor=");
  });

  it("carries the cursor and limit when given", async () => {
    await listTicketEvents("01T1", { cursor: "Y3Vyc29y", limit: 100 });

    expect(urls[0]).toContain("cursor=Y3Vyc29y");
    expect(urls[0]).toContain("limit=100");
  });

  it("omits a null cursor rather than sending the word null", async () => {
    await listTicketEvents("01T1", { cursor: null });

    // `?cursor=null` would be a cursor the server cannot decode.
    expect(urls[0]).not.toContain("cursor=");
  });

  it("escapes the ticket id", async () => {
    await listTicketEvents("a/b", {});

    // An unescaped id would address a different endpoint entirely.
    expect(urls[0]).toContain("a%2Fb");
  });

  it("reads a system actor without a person on it", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(
        async () =>
          new Response(
            JSON.stringify({
              ...PAGE,
              data: [{ ...PAGE.data[0], actor: { type: "system", reason: "auto_close" } }],
            }),
            { status: 200, headers: { "Content-Type": "application/json" } },
          ),
      ),
    );

    const page = await listTicketEvents("01T1");
    const actor = page.data[0]!.actor;

    expect(actor.type).toBe("system");
    expect(actor).not.toHaveProperty("id");
  });
});
