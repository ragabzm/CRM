import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { ApiError, TicketStaleVersionError } from "@/lib/api/errors";
import { request } from "@/lib/api/request";

/** The problem document the server sends when a ticket has moved on. */
function staleBody(overrides: Record<string, unknown> = {}) {
  return {
    type: "https://errors.ragab-crm/tickets.stale_version",
    title: "This ticket was changed by someone else",
    status: 409,
    code: "tickets.stale_version",
    detail: "Someone else changed this ticket while you were editing it.",
    ticket_id: "01TICKET0000000000000000AA",
    current_version: 4,
    submitted_version: 2,
    current: {
      status: "resolved",
      priority: "high",
      category_id: 3,
      assignee_id: 7,
      department_id: 1,
    },
    ...overrides,
  };
}

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

beforeEach(() => respondWith(staleBody(), 409));
afterEach(() => vi.unstubAllGlobals());

/** The rejection, narrowed. Fails the test if the call unexpectedly succeeds. */
async function rejection(): Promise<unknown> {
  try {
    await request("/tickets/x", { method: "PATCH" });
  } catch (caught) {
    return caught;
  }

  throw new Error("Expected the request to reject.");
}

/** Narrows to the stale-version error, asserting the type as it goes. */
async function staleError(): Promise<TicketStaleVersionError> {
  const error = await rejection();

  expect(error).toBeInstanceOf(TicketStaleVersionError);

  return error as TicketStaleVersionError;
}

describe("a stale ticket version is recognised once, in the transport", () => {
  it("throws the specific error rather than a generic one", async () => {
    // Recognised in the transport, not per screen: every ticket write can lose
    // this race, and a per-screen check is one the next screen forgets.
    await expect(request("/tickets/x", { method: "PATCH" })).rejects.toBeInstanceOf(
      TicketStaleVersionError,
    );
  });

  it("parses everything the form needs to recover", async () => {
    const error = await staleError();

    expect(error.ticketId).toBe("01TICKET0000000000000000AA");
    expect(error.currentVersion).toBe(4);
    // All five contended values, so one round trip repopulates the form.
    expect(error.current).toEqual({
      status: "resolved",
      priority: "high",
      category_id: 3,
      assignee_id: 7,
      department_id: 1,
    });
  });

  it("carries the server's own sentence", async () => {
    const error = await staleError();

    expect(error.message).toBe("Someone else changed this ticket while you were editing it.");
    expect(error.status).toBe(409);
  });

  it("is still an ApiError, so existing handlers keep working", async () => {
    expect(await rejection()).toBeInstanceOf(ApiError);
  });

  it("leaves an unrelated 409 as a plain error", async () => {
    respondWith({ title: "Conflict", status: 409, code: "platform.idempotency_conflict" }, 409);

    const error = await rejection();

    expect(error).toBeInstanceOf(ApiError);
    expect(error).not.toBeInstanceOf(TicketStaleVersionError);
  });

  it("does not claim a stale version when the body cannot be read", async () => {
    // A 409 whose payload we cannot parse is still an error; pretending it is
    // this one would leave a screen offering to reload from absent fields.
    respondWith(staleBody({ current: null }), 409);

    expect(await rejection()).not.toBeInstanceOf(TicketStaleVersionError);
  });

  it("does not fire on a non-409 carrying the same code", async () => {
    respondWith(staleBody({ status: 422 }), 422);

    expect(await rejection()).not.toBeInstanceOf(TicketStaleVersionError);
  });

  it("leaves a success alone", async () => {
    respondWith({ id: "01TICKET0000000000000000AA", version: 5 }, 200);

    await expect(request("/tickets/x", { method: "PATCH" })).resolves.toMatchObject({ version: 5 });
  });
});
