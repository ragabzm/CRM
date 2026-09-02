#!/usr/bin/env node
/**
 * Fails if any frontend source reaches into the backend application.
 *
 * The two applications are separately deployable, which only holds if neither
 * can see the other's files. The symmetrical scan is
 * backend/scripts/check-no-cross-import.php.
 *
 * Note the one sanctioned exception: package.json's `api:generate` script reads
 * ../backend/openapi.yaml. That is the contract, not code — the generated
 * client is committed, so a frontend build never needs the backend present.
 * Only source files are scanned, so that script is out of scope by construction.
 */

import { readdirSync, readFileSync, statSync } from "node:fs";
import { dirname, join, relative } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..");

/**
 * Source only. A comment in a .md file mentioning "../backend" is documentation,
 * not a cross-import, and failing on it would train people to ignore this check.
 */
const SCANNED_EXTENSIONS = [".ts", ".tsx", ".js", ".jsx", ".mjs", ".cjs", ".mts"];

const IGNORED_DIRECTORIES = new Set(["node_modules", ".next", ".git", "coverage", "out", "build"]);

/**
 * The only two files exempt from the scan, and both for the same reason: they
 * contain the forbidden strings *as fixtures*, to prove the scan bites. Nothing
 * else gets an exemption — adding to this list defeats the check.
 */
const EXCLUDED_FILES = new Set([
  "scripts/check-no-cross-import.mjs",
  "__tests__/no-cross-import.test.ts",
]);

const FORBIDDEN_PATTERNS = [
  /\bfrom\s+["'][^"']*\.\.\/backend\//,
  /\brequire\s*\(\s*["'][^"']*\.\.\/backend\//,
  /\bimport\s*\(\s*["'][^"']*\.\.\/backend\//,
  /\bimport\s+["'][^"']*\.\.\/backend\//,
];

/** @param {string} dir @returns {string[]} */
function sourceFiles(dir) {
  const found = [];

  for (const entry of readdirSync(dir)) {
    if (IGNORED_DIRECTORIES.has(entry)) continue;

    const full = join(dir, entry);

    if (statSync(full).isDirectory()) {
      found.push(...sourceFiles(full));
    } else if (SCANNED_EXTENSIONS.some((ext) => entry.endsWith(ext))) {
      found.push(full);
    }
  }

  return found;
}

/**
 * @param {string} source
 * @param {string} label
 * @returns {string[]}
 */
export function scanSource(source, label) {
  const violations = [];
  const lines = source.split("\n");

  lines.forEach((line, index) => {
    for (const pattern of FORBIDDEN_PATTERNS) {
      if (pattern.test(line)) {
        violations.push(`  ${label}:${index + 1} ${line.trim()}`);
        break;
      }
    }
  });

  return violations;
}

/** @returns {string[]} */
export function scanRepository() {
  const violations = [];

  for (const file of sourceFiles(ROOT)) {
    const label = relative(ROOT, file);
    if (EXCLUDED_FILES.has(label)) continue;

    violations.push(...scanSource(readFileSync(file, "utf8"), label));
  }

  return violations;
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes("--self-test")) {
    const planted = scanSource(
      `import { thing } from "../backend/app/thing";`,
      "<self-test fixture>",
    );

    if (planted.length === 0) {
      console.error("SELF-TEST FAILED: the scan did not flag a planted cross-import.");
      process.exit(1);
    }

    console.log("SELF-TEST: the scan flagged the planted cross-import as expected.");
    process.exit(1);
  }

  const violations = scanRepository();

  if (violations.length > 0) {
    console.error("frontend/ must not import from backend/:");
    for (const violation of violations) console.error(violation);
    process.exit(1);
  }

  console.log("No cross-imports from frontend/ into backend/.");
}
