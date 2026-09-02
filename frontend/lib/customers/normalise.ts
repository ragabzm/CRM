/**
 * Mirrors the backend's identifier normalisation.
 *
 * A mirror, not the source of truth. It exists so the form can warn about a
 * duplicate row before a round trip and can compare what the agent has typed
 * against what the preview returned — but the server normalises again on
 * arrival, because a client that disagreed with these rules must not be able to
 * create a record the server considers a duplicate of nothing.
 *
 * Kept in step by frontend/__tests__/lib/customers/normalise.test.ts, which
 * asserts the same cases as the backend's IdentifierNormaliserTest.
 */

/** How many trailing digits of a phone number are compared. */
export const PHONE_COMPARISON_DIGITS = 10;

export type ContactKind = "email" | "phone";

function normaliseEmail(value: string): string {
  // Lowercased and stripped of whitespace, including what survives a paste
  // from a mail client.
  return value.trim().replace(/\s+/gu, "").toLowerCase();
}

function normalisePhone(value: string): string {
  const digits = value.replace(/\D+/g, "");

  if (digits === "") return "";

  // The TRAILING digits: a country code and a trunk prefix both sit at the
  // front, and the front is what differs between two ways of writing one
  // number.
  return digits.length > PHONE_COMPARISON_DIGITS ? digits.slice(-PHONE_COMPARISON_DIGITS) : digits;
}

export function normaliseIdentifier(kind: ContactKind, value: string): string {
  return kind === "email" ? normaliseEmail(value) : normalisePhone(value);
}

/** True when two entered values would be stored as the same identifier. */
export function isSameIdentifier(kind: ContactKind, a: string, b: string): boolean {
  const left = normaliseIdentifier(kind, a);

  return left !== "" && left === normaliseIdentifier(kind, b);
}
