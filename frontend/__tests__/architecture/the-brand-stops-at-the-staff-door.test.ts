import { readFileSync, readdirSync, statSync, existsSync } from "node:fs";
import { join, relative, resolve } from "node:path";

import { describe, expect, it } from "vitest";

const ROOT = resolve(__dirname, "../..");

/**
 * The brand reaches four customer surfaces and stops.
 *
 * The customer portal, the public web form, the live-chat widget and outbound
 * email. The staff workspace and the console wear the default design system —
 * not by convention, and not because somebody remembered: the staff layout
 * root never mounts `BrandedSurface`, so there is nothing there for
 * `--brand-primary` to resolve against and any token referencing it falls back
 * to the design system on its own.
 *
 * This test is what keeps that true. The failure it prevents is small and
 * plausible: somebody puts the logo in the staff header "so it looks like
 * ours", and a colour chosen for a customer-facing page — measured only
 * against the surfaces customers see — starts painting controls an agent uses
 * all day.
 */
function filesUnder(dir: string, out: string[] = []): string[] {
  if (!existsSync(dir)) return out;

  for (const entry of readdirSync(dir)) {
    if (entry === "node_modules" || entry === ".next") continue;

    const full = join(dir, entry);

    if (statSync(full).isDirectory()) filesUnder(full, out);
    else if (/\.tsx?$/.test(full)) out.push(full);
  }

  return out;
}

/** Source with its prose removed, so a comment explaining the rule is not a breach of it. */
function codeOnly(source: string): string {
  return source.replace(/\/\*[\s\S]*?\*\//g, "").replace(/^\s*\/\/.*$/gm, "");
}

/** Everything reachable from a route, minus the customer's own trees. */
const STAFF_TREES = [
  join(ROOT, "app", "(app)"),
  join(ROOT, "app", "(auth)"),
  join(ROOT, "components", "screens", "admin"),
  join(ROOT, "components", "screens", "tickets"),
  join(ROOT, "components", "screens", "customers"),
  join(ROOT, "components", "screens", "home"),
  join(ROOT, "components", "screens", "reports"),
];

const BRAND_TOKENS = ["BrandedSurface", "BrandHeader", "--brand-primary", "fetchBranding"];

describe("the brand stops at the staff door", () => {
  it("no staff surface reads a branded token", () => {
    const violations: string[] = [];

    for (const tree of STAFF_TREES) {
      for (const file of filesUnder(tree)) {
        const code = codeOnly(readFileSync(file, "utf8"));

        for (const token of BRAND_TOKENS) {
          if (code.includes(token)) {
            violations.push(`${relative(ROOT, file)} reads ${token}`);
          }
        }
      }
    }

    expect(violations).toEqual([]);
  });

  it("the staff shell does not mount the branded surface", () => {
    /*
     * Checked separately from the sweep above, because this one file is the
     * whole mechanism: if the staff layout root ever wraps its tree in
     * `BrandedSurface`, every rule below it inherits a customer's colour and
     * no other test would notice.
     */
    const shell = readFileSync(join(ROOT, "components", "shell", "AppShell.tsx"), "utf8");

    expect(codeOnly(shell)).not.toContain("BrandedSurface");
  });

  it("the customer surfaces do mount it", () => {
    // The other half. A test that only checks the absence would pass on a
    // product where branding reaches nothing at all.
    const portal = codeOnly(
      readFileSync(join(ROOT, "components", "shell", "portal", "PortalShell.tsx"), "utf8"),
    );

    expect(portal).toContain("BrandedSurface");
    expect(portal).toContain("BrandHeader");
  });

  it("every brand token in the widget has a design-system fallback", () => {
    const frame = readFileSync(join(ROOT, "widget", "frame.html"), "utf8");

    /*
     * Comments stripped first — including HTML ones, which the shared helper
     * above does not remove because it is written for TypeScript. Without
     * this, the comment explaining why a bare `var(--brand-primary)` is wrong
     * fails the test that there is no bare `var(--brand-primary)`.
     */
    const css = codeOnly(frame).replace(/<!--[\s\S]*?-->/g, "");

    const bare = [...css.matchAll(/var\(--brand-[a-z-]+\)/g)];

    /*
     * `var(--brand-primary)` with no fallback resolves to nothing on an
     * unbranded deployment — which is not a missing colour, it is an invisible
     * button. Every reference has to name what it falls back to.
     */
    expect(bare).toEqual([]);
    expect(frame).toMatch(/var\(--brand-primary,\s*var\(--accent-default\)\)/);
  });
});
