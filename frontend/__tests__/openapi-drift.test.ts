import { readFileSync } from "node:fs";

import { describe, expect, it } from "vitest";

import { BANNER, SCHEMA_PATH, render } from "@/scripts/api-client.mjs";

/**
 * The committed client must be exactly what the current contract generates.
 * Anything else means the UI is typed against an API that no longer exists.
 */
describe("generated API client", () => {
  it("matches a fresh generation from backend/openapi.yaml", async () => {
    const committed = readFileSync(SCHEMA_PATH, "utf8");

    expect(await render()).toBe(committed);
  });

  it("carries the do-not-edit banner", () => {
    expect(readFileSync(SCHEMA_PATH, "utf8").startsWith(BANNER)).toBe(true);
  });

  it("exposes the RFC 9457 Problem schema", () => {
    expect(readFileSync(SCHEMA_PATH, "utf8")).toContain("Problem:");
  });
});
