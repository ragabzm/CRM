#!/usr/bin/env node
/**
 * Generates (or drift-checks) lib/api/schema.ts from the backend's OpenAPI
 * document.
 *
 * Generation and checking share one code path deliberately: if `check` produced
 * output even slightly differently from `generate`, CI would fail on a file
 * nobody could regenerate cleanly.
 *
 * Usage:
 *   node scripts/api-client.mjs generate
 *   node scripts/api-client.mjs check
 */

import { readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

import openapiTS, { astToString } from "openapi-typescript";

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..");

export const SCHEMA_PATH = join(ROOT, "lib/api/schema.ts");

/**
 * In CI the backend job publishes openapi.yaml as an artifact and the frontend
 * job downloads it to frontend/openapi.yaml — so the frontend pipeline never
 * needs the backend source tree. Locally the sibling checkout is used instead.
 */
const SPEC_CANDIDATES = [join(ROOT, "openapi.yaml"), join(ROOT, "../backend/openapi.yaml")];

export const BANNER = [
  "// GENERATED FILE — DO NOT EDIT. Regenerate via `pnpm run api:generate`.",
  "// Source of truth: backend/openapi.yaml",
  "",
].join("\n");

function specPath() {
  for (const candidate of SPEC_CANDIDATES) {
    try {
      readFileSync(candidate);
      return candidate;
    } catch {
      // try the next candidate
    }
  }

  throw new Error(`No OpenAPI document found. Looked in:\n  ${SPEC_CANDIDATES.join("\n  ")}`);
}

export async function render() {
  const ast = await openapiTS(pathToFileURL(specPath()));

  return BANNER + astToString(ast);
}

const command = process.argv[2] ?? "generate";

if (command === "generate") {
  const output = await render();
  writeFileSync(SCHEMA_PATH, output);
  console.log(`api:generate: wrote ${SCHEMA_PATH}`);
} else if (command === "check") {
  const fresh = await render();
  const committed = readFileSync(SCHEMA_PATH, "utf8");

  if (fresh === committed) {
    console.log("api:check: lib/api/schema.ts is up to date.");
  } else {
    console.error(
      "api:check: lib/api/schema.ts is stale — it does not match backend/openapi.yaml.",
    );
    console.error("  Remediation: run `pnpm run api:generate` and commit the result.");
    process.exit(1);
  }
} else {
  console.error(`Unknown command "${command}". Expected "generate" or "check".`);
  process.exit(64);
}
