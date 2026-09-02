import { describe, expect, it } from "vitest";

import {
  isSameIdentifier,
  normaliseIdentifier,
  PHONE_COMPARISON_DIGITS,
} from "@/lib/customers/normalise";

const email = (value: string) => normaliseIdentifier("email", value);
const phone = (value: string) => normaliseIdentifier("phone", value);

/**
 * These mirror backend/tests/Unit/Customers/IdentifierNormaliserTest.php case
 * for case. Two implementations of one rule drift silently unless the same
 * examples are asserted on both sides.
 */
describe("identifier normalisation mirrors the backend", () => {
  it("lowercases and trims an email", () => {
    expect(email("  Hana@Example.TEST  ")).toBe("hana@example.test");
  });

  it("survives a paste from a mail client", () => {
    expect(email("hana@example.test\n")).toBe("hana@example.test");
    expect(email("hana @example.test")).toBe("hana@example.test");
  });

  it("collapses the same number written two ways", () => {
    // The case the whole mechanism exists for.
    expect(phone("+44 20 7946 0958")).toBe(phone("020 7946 0958"));
    expect(phone("(555) 123-4567")).toBe(phone("+1 555 123 4567"));
  });

  it("ignores punctuation and spacing", () => {
    expect(phone("555.123.4567")).toBe(phone("555 123 4567"));
    expect(phone("555-123-4567")).toBe(phone("5551234567"));
  });

  it("compares the trailing digits", () => {
    expect(phone("+44 20 7946 0958 12")).toBe("7946095812");
    expect(phone("+44 20 7946 0958 12")).toHaveLength(PHONE_COMPARISON_DIGITS);
  });

  it("keeps a short number whole", () => {
    expect(phone("4567")).toBe("4567");
  });

  it("returns nothing for a value with no digits", () => {
    expect(phone("call the office")).toBe("");
    expect(phone("---")).toBe("");
  });

  it("returns nothing for an empty email", () => {
    expect(email("   ")).toBe("");
  });

  it("is stable when applied twice", () => {
    // Or a re-check would stop matching what is already stored.
    expect(phone(phone("+44 20 7946 0958"))).toBe(phone("+44 20 7946 0958"));
    expect(email(email("Hana@Example.test"))).toBe(email("Hana@Example.test"));
  });

  it("keeps different people different", () => {
    expect(email("hana@example.test")).not.toBe(email("omar@example.test"));
    expect(phone("555 123 4567")).not.toBe(phone("555 123 4568"));
  });

  it("recognises two spellings of one identifier", () => {
    expect(isSameIdentifier("phone", "+44 20 7946 0958", "020 7946 0958")).toBe(true);
    expect(isSameIdentifier("email", "Hana@Example.test", "hana@example.test")).toBe(true);
  });

  it("does not call two empty values the same", () => {
    // Otherwise every blank row in the form would flag every other blank row.
    expect(isSameIdentifier("phone", "", "")).toBe(false);
    expect(isSameIdentifier("email", "  ", "")).toBe(false);
  });
});
