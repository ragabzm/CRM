import { readFileSync, readdirSync, statSync } from "node:fs";
import { join } from "node:path";

import { describe, expect, it } from "vitest";

/**
 * The chrome is mounted exactly once.
 *
 * It was mounted four times: the root layout, the admin layout, and two
 * customer screens that wrapped themselves because they could not tell whether
 * they were already inside it. The result was a sidebar and top bar rendered
 * INSIDE the sidebar and top bar on `/customers` and `/admin/*` — two identical
 * navigations on screen at once.
 *
 * `components/shell/README.md` already said "mounted once". Nothing enforced
 * it, so it drifted three times over. This is the enforcement.
 */

const ROOTS = ["app", "components"];
const ALLOWED = "app/(app)/layout.tsx";

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

/** Comments explain the rule; only real JSX counts as a mount. */
function withoutComments(source: string): string {
  return source.replace(/\/\*[\s\S]*?\*\//g, "").replace(/\/\/.*$/gm, "");
}

describe("the app shell", () => {
  const mounts = ROOTS.flatMap(sourceFiles)
    .filter((path) => !path.includes("components/shell/"))
    .filter((path) => /<AppShell[\s>]/.test(withoutComments(readFileSync(path, "utf8"))));

  it("is mounted in exactly one place", () => {
    expect(mounts).toEqual([ALLOWED]);
  });

  it("is not mounted by the root layout, which also wraps sign-in", () => {
    // Chrome there means a signed-out visitor sees a sidebar full of
    // destinations they cannot reach.
    expect(mounts).not.toContain("app/layout.tsx");
  });

  it("scanned a believable number of files", () => {
    // A broken walk would find zero mounts and pass silently.
    expect(ROOTS.flatMap(sourceFiles).length).toBeGreaterThan(50);
  });
});
