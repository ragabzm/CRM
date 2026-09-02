import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import en from "@/messages/en.json";
import { ApiError, TICKET_REFUSALS, TicketRefusedError } from "@/lib/api/errors";
import { request } from "@/lib/api/request";

/**
 * Every ticket refusal is recognised in one place — the transport — so a screen
 * catches one error type and renders the right sentence, and adding a refusal
 * server-side is a line in one table rather than an edit in every surface that
 * writes tickets.
 */

function respondWith(body: unknown, status: number) {
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

async function rejection(): Promise<unknown> {
  try {
    await request("/tickets/x", { method: "PATCH" });
  } catch (caught) {
    return caught;
  }

  throw new Error("Expected the request to reject.");
}

beforeEach(() => respondWith({}, 200));
afterEach(() => vi.unstubAllGlobals());

const REFUSALS = [
  ["tickets.transition_forbidden", 409],
  ["tickets.reassign_forbidden", 403],
  ["tickets.assignee_invalid", 422],
  ["tickets.department_invalid", 422],
] as const;

describe("ticket refusals surface as one typed error", () => {
  it.each(REFUSALS)("recognises %s", async (code, status) => {
    respondWith({ code, status, title: "Refused", detail: "Because." }, status);

    const error = await rejection();

    expect(error).toBeInstanceOf(TicketRefusedError);
    expect((error as TicketRefusedError).messageKey).toBe(TICKET_REFUSALS[code]);
    expect((error as TicketRefusedError).status).toBe(status);
  });

  it("carries the server's own sentence", async () => {
    respondWith(
      {
        code: "tickets.transition_forbidden",
        status: 409,
        detail: "A open ticket cannot become closed.",
      },
      409,
    );

    expect((await rejection()) as Error).toHaveProperty(
      "message",
      "A open ticket cannot become closed.",
    );
  });

  it("parses the way forward on an expired reopen window", async () => {
    respondWith(
      {
        code: "tickets.reopen_window_expired",
        status: 409,
        detail: "This ticket closed 30 days ago.",
        reopen_window_days: 14,
        new_request_hint: {
          action: "create_ticket",
          path: "/tickets/new?from=01T",
          customer_id: "01C",
        },
      },
      409,
    );

    const error = (await rejection()) as TicketRefusedError;

    // "No" on its own leaves an agent with a customer on the line and nothing
    // to offer, so the refusal carries the route to a new request.
    expect(error).toBeInstanceOf(TicketRefusedError);
    expect(error.reopenWindowDays).toBe(14);
    expect(error.newRequestHint).toEqual({
      action: "create_ticket",
      path: "/tickets/new?from=01T",
      customer_id: "01C",
    });
  });

  it("leaves the hint null on refusals that carry none", async () => {
    respondWith({ code: "tickets.assignee_invalid", status: 422 }, 422);

    const error = (await rejection()) as TicketRefusedError;

    expect(error.newRequestHint).toBeNull();
    expect(error.reopenWindowDays).toBeNull();
  });

  it("is still an ApiError, so existing handlers keep working", async () => {
    respondWith({ code: "tickets.transition_forbidden", status: 409 }, 409);

    expect(await rejection()).toBeInstanceOf(ApiError);
  });

  it("leaves an unrelated refusal as a plain error", async () => {
    respondWith({ code: "platform.idempotency_conflict", status: 409 }, 409);

    const error = await rejection();

    expect(error).toBeInstanceOf(ApiError);
    expect(error).not.toBeInstanceOf(TicketRefusedError);
  });

  it("every mapped code has copy in both languages", async () => {
    const messages = en.tickets.errors as Record<string, string>;

    for (const key of Object.values(TICKET_REFUSALS)) {
      // The table maps to `tickets.errors.<name>`; the parity test guarantees
      // Arabic once English exists.
      const name = key.replace("tickets.errors.", "");

      expect(messages[name], `${key} has no English copy`).toBeTruthy();
    }
  });

  it("names a way forward in the expired-window copy", () => {
    // The one refusal an agent cannot act on without an alternative.
    expect(en.tickets.errors.reopenWindowExpired).toContain("{days}");
    expect(en.tickets.errors.startNewRequest).toBeTruthy();
  });
});
