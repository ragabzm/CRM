"use client";

import type { Problem } from "./client";

/**
 * An API response that was not a success, carrying its problem document.
 *
 * Declared here rather than in request.ts so TicketStaleVersionError can extend
 * it without the two modules importing each other.
 */
export class ApiError extends Error {
  constructor(
    message: string,
    readonly problem: Problem | null,
    readonly status: number,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

/** The five properties two people genuinely contend over on a ticket. */
export interface TicketContendedValues {
  status: string;
  priority: string;
  category_id: number | null;
  assignee_id: number | null;
  department_id: number | null;
}

/** The problem code the server uses when a ticket has moved on. */
export const TICKET_STALE_VERSION = "tickets.stale_version";

/**
 * Every refusal a ticket write can come back with, and the message key that
 * explains it.
 *
 * One table, so a screen catching `TicketRefusedError` renders the right
 * sentence without a switch of its own — and adding a refusal server-side is a
 * line here rather than an edit in every surface that writes tickets.
 */
export const TICKET_REFUSALS: Record<string, string> = {
  "tickets.transition_forbidden": "tickets.errors.transitionForbidden",
  "tickets.reopen_window_expired": "tickets.errors.reopenWindowExpired",
  "tickets.reassign_forbidden": "tickets.errors.reassignForbidden",
  "tickets.assignee_invalid": "tickets.errors.assigneeInvalid",
  "tickets.department_invalid": "tickets.errors.departmentInvalid",
};

/** What a reopen-window refusal offers instead. */
export interface NewRequestHint {
  action: string;
  path: string;
  customer_id: string;
}

/**
 * A ticket write the server refused on a domain rule.
 *
 * Distinct from a plain ApiError so a screen can catch exactly these and show
 * the server's reason with the right copy — and, for an expired reopen window,
 * the "start a new request" route the refusal carries. A refusal that only says
 * no leaves an agent with a customer on the line and nothing to offer.
 */
export class TicketRefusedError extends ApiError {
  /** The i18n key for this refusal. */
  readonly messageKey: string;

  /** Present only on `tickets.reopen_window_expired`. */
  readonly newRequestHint: NewRequestHint | null;

  /** Present only on `tickets.reopen_window_expired`. */
  readonly reopenWindowDays: number | null;

  constructor(message: string, problem: ApiError["problem"], status: number, messageKey: string) {
    super(message, problem, status);
    this.name = "TicketRefusedError";
    this.messageKey = messageKey;

    const body = (problem ?? {}) as Record<string, unknown>;
    const hint = body.new_request_hint;

    this.newRequestHint =
      hint !== null && typeof hint === "object" ? (hint as unknown as NewRequestHint) : null;
    this.reopenWindowDays =
      typeof body.reopen_window_days === "number" ? body.reopen_window_days : null;
  }

  static from(problem: unknown, status: number): TicketRefusedError | null {
    if (problem === null || typeof problem !== "object") return null;

    const code = (problem as Record<string, unknown>).code;

    if (typeof code !== "string") return null;

    const messageKey = TICKET_REFUSALS[code];

    if (messageKey === undefined) return null;

    const detail = (problem as Record<string, unknown>).detail;

    return new TicketRefusedError(
      typeof detail === "string" ? detail : "That change was refused.",
      problem as ApiError["problem"],
      status,
      messageKey,
    );
  }
}

/**
 * Someone else changed the ticket while this edit was in flight.
 *
 * A distinct error type rather than a status code check, so a screen can catch
 * exactly this and offer a reload — and so the recovery data the server sent
 * arrives already parsed, instead of every caller reaching into the problem
 * document and disagreeing about the shape.
 */
export class TicketStaleVersionError extends ApiError {
  readonly ticketId: string;

  readonly currentVersion: number;

  /** What the ticket says NOW, so the form can repopulate in one round trip. */
  readonly current: TicketContendedValues;

  constructor(
    message: string,
    problem: ApiError["problem"],
    fields: { ticketId: string; currentVersion: number; current: TicketContendedValues },
  ) {
    super(message, problem, 409);
    this.name = "TicketStaleVersionError";
    this.ticketId = fields.ticketId;
    this.currentVersion = fields.currentVersion;
    this.current = fields.current;
  }

  /**
   * Builds the error when the body carries what recovery needs.
   *
   * Returns null when it does not: a 409 whose payload we cannot read is still
   * an error, and pretending it is a stale-version conflict would leave a
   * screen offering to reload from fields that are not there.
   */
  static from(problem: unknown, status: number): TicketStaleVersionError | null {
    if (status !== 409 || problem === null || typeof problem !== "object") return null;

    const body = problem as Record<string, unknown>;

    if (body.code !== TICKET_STALE_VERSION) return null;

    const current = body.current;

    if (
      typeof body.ticket_id !== "string" ||
      typeof body.current_version !== "number" ||
      current === null ||
      typeof current !== "object"
    ) {
      return null;
    }

    return new TicketStaleVersionError(
      typeof body.detail === "string" ? body.detail : "This ticket was changed by someone else.",
      body as ApiError["problem"],
      {
        ticketId: body.ticket_id,
        currentVersion: body.current_version,
        current: current as unknown as TicketContendedValues,
      },
    );
  }
}
