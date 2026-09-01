#!/usr/bin/env node
/**
 * Belt-and-braces token check over components/.
 *
 * The ESLint rule `design-system/semantic-tokens-only` is the primary guard.
 * This script exists because that rule can be switched off with an inline
 * directive, and a colour literal that reaches main is not a style nit — it is
 * a value nobody can retheme. A separate CI step that does not read
 * eslint-disable comments closes that door.
 *
 * Usage:
 *   node scripts/check-tokens.mjs           # scan components/
 *   node scripts/check-tokens.mjs <dir>     # scan a specific directory
 */

import { readdirSync, readFileSync, statSync } from "node:fs";
import { dirname, join, relative, resolve } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..");

const SCANNED_EXTENSIONS = [".ts", ".tsx", ".js", ".jsx", ".mjs"];

const IGNORED_DIRECTORIES = new Set(["node_modules", ".next", ".git", "coverage", "out", "build"]);

/** Primitive palette scales. Declared in tokens.css for Tailwind's benefit only. */
const PRIMITIVE_SCALES = "n|status|sla|priority|channel|cat|ord|chart|ai";

const PRIMITIVE_UTILITY = new RegExp(
  `(?:^|["'\\s\`])(?:[\\w[\\]&>:._-]+:)*(?:bg|text|border|ring|fill|stroke|outline|shadow|from|to|via|divide)-(?:${PRIMITIVE_SCALES})-[\\w./-]+`,
  "g",
);

const COLOUR_LITERAL = /#[0-9a-fA-F]{3,8}\b|\brgba?\(|\boklch\(|\bhsla?\(|\bcolor-mix\(/g;

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

  source.split("\n").forEach((line, index) => {
    for (const [pattern, reason] of [
      [PRIMITIVE_UTILITY, "primitive palette name"],
      [COLOUR_LITERAL, "raw colour value"],
    ]) {
      pattern.lastIndex = 0;
      const match = pattern.exec(line);
      if (match) {
        violations.push(`  ${label}:${index + 1} ${reason}: ${match[0].trim()}`);
        break;
      }
    }
  });

  return violations;
}

/** @param {string} dir @returns {string[]} */
export function scanDirectory(dir) {
  const violations = [];

  for (const file of sourceFiles(dir)) {
    violations.push(...scanSource(readFileSync(file, "utf8"), relative(ROOT, file)));
  }

  return violations;
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const target = process.argv[2] ? resolve(process.cwd(), process.argv[2]) : join(ROOT, "components");

  const violations = scanDirectory(target);

  if (violations.length > 0) {
    console.error("check-tokens: components must reference semantic tokens, never primitives or literals.");
    for (const violation of violations) console.error(violation);
    console.error("\n  Remediation: use a semantic token (bg-surface-raised, text-fg-muted). See tokens/tokens.css.");
    process.exit(1);
  }

  console.log(`check-tokens: no primitives or colour literals under ${relative(ROOT, target) || "."}.`);
}
