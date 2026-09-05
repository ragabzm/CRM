import { readFileSync, readdirSync, statSync } from "node:fs";
import { join, relative, resolve } from "node:path";

import { describe, expect, it } from "vitest";

/**
 * The column that says WHICH record a row is opens that record.
 *
 * `DataTable`'s `identity: true` marks the one column a reader uses to tell
 * rows apart. The ticket list made it a real `<Link>`; the customer list left
 * it a `<span>`, so the only way to open a customer was the `…` menu at the
 * end of the row — two clicks through something hidden, and no way at all to
 * open one in a new tab, copy its address, or send it to a colleague.
 *
 * Fixing each table as somebody notices it is how the third table repeats the
 * mistake. This is the rule, checked once.
 *
 * It reads source rather than rendering, because the property is structural:
 * a rendering test would need every table's fixtures and would still only
 * cover the tables somebody remembered to write one for.
 */

const ROOT = resolve(__dirname, "../..");

function filesUnder(dir: string, out: string[] = []): string[] {
  for (const entry of readdirSync(dir)) {
    if (entry === "node_modules" || entry === ".next") continue;

    const full = join(dir, entry);

    if (statSync(full).isDirectory()) filesUnder(full, out);
    else if (full.endsWith(".tsx")) out.push(full);
  }

  return out;
}

/** The column definition an `identity: true` appears in, roughly bounded. */
function identityBlocks(source: string): string[] {
  const blocks: string[] = [];

  let from = 0;

  for (;;) {
    const at = source.indexOf("identity: true", from);

    if (at === -1) break;

    /*
     * From the opening of this column object to the start of the next one.
     * Crude, and enough: a column definition is a short literal and the `cell`
     * belongs to whichever one it sits inside.
     */
    const start = source.lastIndexOf("{", at);
    const next = source.indexOf("\n    },", at);

    blocks.push(source.slice(start, next === -1 ? source.length : next));
    from = at + 1;
  }

  return blocks;
}

/**
 * Tables whose rows have no page of their own, each with its reason.
 *
 * The rule is not "every identity column is a link" — it is "a row that HAS a
 * destination is reachable by one". A category has no `/categories/{id}`; a
 * mail-log row opens a dialog because there is nothing else to show. Those are
 * correct as they are.
 *
 * The list is deliberately awkward to add to: putting a table here is claiming
 * its rows genuinely lead nowhere, and that claim should be uncomfortable to
 * make.
 */
const NO_PAGE_OF_ITS_OWN: Record<string, string> = {
  "components/domain/CategoryList/CategoryList.tsx":
    "a category is edited in a dialog; there is no category page",
  "components/domain/MailLogTable/MailLogTable.tsx":
    "a delivery attempt is a log line, not a record with a page",
  "components/domain/MailQuarantine/MailQuarantineTable.tsx":
    "a quarantined message opens a dialog holding the raw source",
  "components/screens/admin/AuditLogScreen.tsx":
    "an audit entry opens its before/after panel in place; it is immutable and has no page",
  "components/screens/admin/OrganisationSection.tsx":
    "a department and a staff account are both edited in place",
};

describe("every identity column", () => {
  const all = filesUnder(join(ROOT, "components"))
    .filter((file) => readFileSync(file, "utf8").includes("identity: true"))
    .map((file) => relative(ROOT, file));

  const tables = all.filter((file) => NO_PAGE_OF_ITS_OWN[file] === undefined);

  it("is found by the scan at all", () => {
    // Guarding the guard: a scan that matches nothing passes every case below.
    expect(all.length).toBeGreaterThanOrEqual(5);
    expect(tables.length).toBeGreaterThanOrEqual(2);
  });

  it("that is excused says why", () => {
    // An entry with an empty reason is a table somebody silenced rather than
    // thought about.
    for (const [file, reason] of Object.entries(NO_PAGE_OF_ITS_OWN)) {
      expect(all, `${file} is in the exclusion list but has no identity column`).toContain(file);
      expect(reason.length).toBeGreaterThan(20);
    }
  });

  it.each(tables)("in %s opens its record with a real link", (file) => {
    const blocks = identityBlocks(readFileSync(join(ROOT, file), "utf8"));

    expect(blocks.length).toBeGreaterThan(0);

    for (const block of blocks) {
      /*
       * A `<Link>`, not a click handler on a div. Middle-click, ⌘-click and
       * "copy link address" are things the browser already does correctly, and
       * a handler that calls `router.push` throws all three away.
       */
      expect(
        block.includes("<Link"),
        `The identity column in ${file} is not a link, so its row cannot be opened in a new tab or copied.`,
      ).toBe(true);

      // And it must leave modified clicks to the browser.
      expect(
        block.includes("metaKey") && block.includes("ctrlKey"),
        `The identity column in ${file} swallows modified clicks.`,
      ).toBe(true);
    }
  });
});
