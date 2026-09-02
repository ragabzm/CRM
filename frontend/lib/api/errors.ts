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
