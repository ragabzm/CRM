import { readFileSync, readdirSync, statSync } from "node:fs";
import { join } from "node:path";

import { describe, expect, it } from "vitest";

/**
 * The customer surface has no organisation, company or account concept.
 *
 * The frontend half of the rule enforced on the backend by
 * `NoOrganisationFieldTest`. It has to live here rather than there: the two
 * apps are separately deployable and `composer no-cross-import` fails the build
 * if backend/ so much as names a path under frontend/.
 *
 * Out of scope by decision — and the way that decision gets reversed is not a
 * design discussion but somebody adding a "company" field because a form had a
 * spare slot. A column is easy to add and almost impossible to remove.
 */

const FORBIDDEN = ["organisation", "organization", "company"];

/**
 * Scoped to the customer surface, not all of `frontend/`.
 *
 * "account" and "company" appear legitimately elsewhere — portal accounts, the
 * sign-in copy — and a guard that cried wolf there would be switched off.
 */
const SCANNED = [
  "components/screens/customers",
  "components/domain/CustomerFormDialog",
  "components/domain/CustomerEditor",
  "components/domain/DuplicateOffer",
  "components/domain/CustomerList",
  "lib/customers",
  "lib/api/customers.ts",
];

function filesUnder(relative: string): string[] {
  const path = join(process.cwd(), relative);

  let entry;
  try {
    entry = statSync(path);
  } catch {
    return [];
  }

  if (entry.isFile()) return [path];

  return readdirSync(path).flatMap((name) => filesUnder(join(relative, name)));
}

/**
 * Strips comments before matching.
 *
 * The same reason the backend sweep uses PHP's tokeniser: a comment explaining
 * why there is no organisation field must not itself read as one, or the rule
 * becomes an incentive to write worse comments.
 */
function withoutComments(source: string): string {
  return source.replace(/\/\*[\s\S]*?\*\//g, "").replace(/(^|\s)\/\/[^\n]*/g, "$1");
}

describe("the customer surface has no organisation concept", () => {
  const files = SCANNED.flatMap(filesUnder).filter((f) => f.endsWith(".ts") || f.endsWith(".tsx"));

  it("actually found files to check", () => {
    // A path typo would otherwise make every assertion below pass by scanning
    // nothing at all.
    expect(files.length).toBeGreaterThan(5);
  });

  it.each(FORBIDDEN)("never mentions %s", (word) => {
    const offenders = files.filter((file) =>
      withoutComments(readFileSync(file, "utf8")).toLowerCase().includes(word),
    );

    expect(offenders.map((f) => f.replace(process.cwd() + "/", ""))).toEqual([]);
  });

  it("would catch a violation", () => {
    // A guard that cannot fail is not a guard.
    const stripped = withoutComments("// no organisation here\nconst company = 1;").toLowerCase();

    expect(stripped).toContain("company");
    expect(stripped).not.toContain("organisation");
  });
});
