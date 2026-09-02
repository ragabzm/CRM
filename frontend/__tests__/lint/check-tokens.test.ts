import { execFileSync } from "node:child_process";
import { mkdtempSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

import { describe, expect, it } from "vitest";

import { scanSource } from "@/scripts/check-tokens.mjs";

const SCRIPT = join(process.cwd(), "scripts/check-tokens.mjs");

function runAgainst(dir: string) {
  try {
    execFileSync("node", [SCRIPT, dir], { encoding: "utf8" });
    return 0;
  } catch (error) {
    return (error as { status?: number }).status ?? 1;
  }
}

describe("check-tokens.mjs", () => {
  it("exits 0 on the real components tree", () => {
    expect(runAgainst(join(process.cwd(), "components"))).toBe(0);
  });

  it("exits non-zero on a fixture containing a primitive", () => {
    const dir = mkdtempSync(join(tmpdir(), "token-fixture-"));
    writeFileSync(
      join(dir, "Bad.tsx"),
      `export const B = () => <div className="bg-n-800">x</div>;`,
    );

    expect(runAgainst(dir)).not.toBe(0);
  });

  it("exits non-zero on a fixture containing a hex literal", () => {
    const dir = mkdtempSync(join(tmpdir(), "token-fixture-"));
    writeFileSync(
      join(dir, "Bad.tsx"),
      `export const B = () => <div style={{ color: "#abcdef" }} />;`,
    );

    expect(runAgainst(dir)).not.toBe(0);
  });

  it("catches what an eslint-disable would hide", () => {
    /*
     * The whole reason this script exists: the ESLint rule can be switched off
     * inline, and a colour literal that reaches main is a value nobody can
     * retheme. This scan does not read disable directives.
     */
    const source = `// eslint-disable-next-line design-system/semantic-tokens-only\nexport const B = () => <div className="bg-n-800">x</div>;`;

    expect(scanSource(source, "Bad.tsx").length).toBeGreaterThan(0);
  });

  it("does not flag semantic tokens", () => {
    const source = `export const G = () => <div className="bg-surface-raised text-fg-muted">x</div>;`;

    expect(scanSource(source, "Good.tsx")).toEqual([]);
  });
});
