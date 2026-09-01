#!/usr/bin/env node
/**
 * Pins the Next.js version to the 16.3 security release.
 *
 * Story 1.1 requires "the 16.3.x security release dated 2026-08-26 or later,
 * never 16.3.2". 16.3.3 is the release that superseded 16.3.2; note its npm
 * publish timestamp is 2026-08-25T15:32Z, one day before the date the story
 * quotes, so this guard keys off the *version*, not the date. If a later 16.3.x
 * ships, raise MINIMUM_VERSION rather than widening the check.
 */

import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { dirname, join } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const MINIMUM_VERSION = "16.3.3";
const FORBIDDEN_VERSIONS = ["16.3.2"];
const REQUIRED_MAJOR_MINOR = "16.3";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");

/** @param {string} version */
function parse(version) {
  const match = /^(\d+)\.(\d+)\.(\d+)/.exec(version);
  if (!match) return null;
  return [Number(match[1]), Number(match[2]), Number(match[3])];
}

/** @returns {-1 | 0 | 1} */
function compare(a, b) {
  const left = parse(a);
  const right = parse(b);
  if (!left || !right) return 0;

  for (let i = 0; i < 3; i++) {
    if (left[i] !== right[i]) return left[i] < right[i] ? -1 : 1;
  }
  return 0;
}

/**
 * Prefer the version actually installed: package.json can say "16.3.3" while a
 * stale lockfile or a caret range resolves to something else, and it is the
 * resolved version that ships.
 */
function resolveNextVersion() {
  try {
    const require = createRequire(join(root, "package.json"));
    return require("next/package.json").version;
  } catch {
    const pkg = JSON.parse(readFileSync(join(root, "package.json"), "utf8"));
    return pkg.dependencies?.next?.replace(/^[\^~]/, "") ?? null;
  }
}

/**
 * @param {string | null} version
 * @returns {string[]} Empty when the version is acceptable.
 */
export function validate(version) {
  const failures = [];

  if (!version) {
    return ["Could not determine the Next.js version."];
  }

  if (FORBIDDEN_VERSIONS.includes(version)) {
    failures.push(`next ${version} is explicitly forbidden by Story 1.1.`);
  }

  if (!version.startsWith(`${REQUIRED_MAJOR_MINOR}.`)) {
    failures.push(`next ${version} is outside the required ${REQUIRED_MAJOR_MINOR}.x line.`);
  }

  if (compare(version, MINIMUM_VERSION) < 0) {
    failures.push(`next ${version} is older than the required security release ${MINIMUM_VERSION}.`);
  }

  return failures;
}

export { MINIMUM_VERSION, FORBIDDEN_VERSIONS, REQUIRED_MAJOR_MINOR, compare, resolveNextVersion };

// Only act when run as a command; the tests import the pieces above.
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const version = resolveNextVersion();
  const failures = validate(version);

  if (failures.length > 0) {
    console.error("check-next-version: FAILED");
    for (const failure of failures) console.error(`  - ${failure}`);
    console.error(`\n  Remediation: pnpm add next@${MINIMUM_VERSION} && pnpm install`);
    process.exit(1);
  }

  console.log(
    `check-next-version: next ${version} satisfies >= ${MINIMUM_VERSION} (and is not ${FORBIDDEN_VERSIONS.join(", ")}).`,
  );
}
