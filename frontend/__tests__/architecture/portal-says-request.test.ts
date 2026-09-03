import { readFileSync, readdirSync, statSync } from "node:fs";
import { join } from "node:path";

import { describe, expect, it } from "vitest";

import ar from "@/messages/ar.json";
import en from "@/messages/en.json";

/**
 * Customer-facing copy says REQUEST, never "ticket".
 *
 * "Ticket" is what the desk calls it internally. Somebody who asked a question
 * about their invoice did not file a ticket, and being told they did makes the
 * business sound like a system rather than people. It is a small word that sets
 * the whole tone of the customer's half of the product.
 *
 * The URLs still say `/portal/requests`, and the API still says `tickets` —
 * that is the internal vocabulary, and it is not what a customer reads.
 */

function values(node: unknown, found: string[] = []): string[] {
  if (typeof node === "string") {
    found.push(node);
  } else if (typeof node === "object" && node !== null) {
    for (const child of Object.values(node)) values(child, found);
  }

  return found;
}

describe("the portal's words", () => {
  it("never says ticket in English", () => {
    const offenders = values(en.portal).filter((text) => /\bticket/i.test(text));

    expect(offenders).toEqual([]);
  });

  it("never says تذكرة in Arabic", () => {
    const offenders = values(ar.portal).filter((text) => /تذكرة|تذاكر/.test(text));

    expect(offenders).toEqual([]);
  });

  it("says request where it needs a word for one", () => {
    // The negative tests above pass trivially if the namespace is empty.
    const all = values(en.portal).join(" ");

    expect(all).toMatch(/request/i);
    expect(values(en.portal).length).toBeGreaterThan(15);
  });

  it("keeps that vocabulary out of the portal components too", () => {
    const offenders: string[] = [];

    for (const file of sourceFiles("components/screens/portal")) {
      const code = readFileSync(file, "utf8");

      // Only visible text: `tickets` appears legitimately in API paths and
      // type names, which no customer reads.
      for (const match of code.matchAll(/>([^<>{}\n]*\bticket[^<>{}\n]*)</gi)) {
        offenders.push(`${file}: ${match[1]!.trim()}`);
      }
    }

    expect(offenders).toEqual([]);
  });
});

function sourceFiles(dir: string): string[] {
  const found: string[] = [];

  for (const name of readdirSync(dir)) {
    const path = join(dir, name);

    if (statSync(path).isDirectory()) found.push(...sourceFiles(path));
    else if (/\.tsx?$/.test(name)) found.push(path);
  }

  return found;
}
