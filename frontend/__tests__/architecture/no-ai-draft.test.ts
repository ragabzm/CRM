import { readFileSync, readdirSync, statSync } from "node:fs";
import { join } from "node:path";

import { describe, expect, it } from "vitest";

/**
 * There is no AI-draft treatment, and there is not going to be one by accident.
 *
 * The workspace mockup showed one; this story deliberately did not build it. A
 * machine-written suggestion styled like an agent's own reply is a fourth thing
 * the reader has to learn to distinguish from the three that already exist —
 * and the failure mode is an agent sending a draft they only skimmed, in their
 * own name.
 *
 * If that panel is ever wanted, it should arrive as a decision somebody made,
 * not as a file that appeared.
 */

const ROOTS = ["app", "components", "lib"];

function sourceFiles(dir: string): string[] {
  const found: string[] = [];

  for (const name of readdirSync(dir)) {
    if (name === "node_modules" || name.startsWith(".")) continue;

    const path = join(dir, name);

    if (statSync(path).isDirectory()) {
      found.push(...sourceFiles(path));
    } else if (/\.tsx?$/.test(name)) {
      found.push(path);
    }
  }

  return found;
}

/** Comments explain the rule; only real code counts as an implementation. */
function withoutComments(source: string): string {
  return source.replace(/\/\*[\s\S]*?\*\//g, "").replace(/\/\/.*$/gm, "");
}

describe("the conversation", () => {
  const files = ROOTS.flatMap(sourceFiles);

  it("has no AI suggestion panel", () => {
    const offenders = files.filter((path) => /AiSuggestion|AIDraft|AiDraft/i.test(path));

    expect(offenders).toEqual([]);
  });

  it("has no ai-draft treatment in any component", () => {
    const offenders = files.filter((path) =>
      /["']ai[_-]?draft["']|data-direction=["']ai/i.test(
        withoutComments(readFileSync(path, "utf8")),
      ),
    );

    expect(offenders).toEqual([]);
  });

  it("keeps exactly three message directions", () => {
    const client = readFileSync("lib/api/tickets.ts", "utf8");

    // A fourth would have to be added here first, which makes adding one a
    // visible decision rather than a styling accident.
    expect(client).toContain('export type MessageDirection = "inbound" | "outbound" | "internal";');
  });

  it("scanned a believable number of files", () => {
    // A broken walk would find no offenders and pass every test above.
    expect(files.length).toBeGreaterThan(50);
  });
});
