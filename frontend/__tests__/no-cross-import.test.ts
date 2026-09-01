import { describe, expect, it } from "vitest";

import { scanRepository, scanSource } from "@/scripts/check-no-cross-import.mjs";

/**
 * The frontend half of the two-way independence proof. The backend half is
 * backend/tests/Architecture/NoCrossImportTest.php.
 */
describe("frontend does not import from backend", () => {
  it("finds no cross-imports in the repository", () => {
    expect(scanRepository()).toEqual([]);
  });

  it.each([
    `import { thing } from "../backend/app/thing";`,
    `import { thing } from "../../backend/app/Modules/Platform/thing";`,
    `const thing = require("../backend/thing");`,
    `const thing = await import("../backend/thing");`,
  ])("flags %s", (source) => {
    expect(scanSource(source, "fixture.ts")).not.toEqual([]);
  });

  it.each([
    `// see ../backend/openapi.yaml for the contract`,
    `const label = "backend";`,
    `import { createApiClient } from "@/lib/api/client";`,
  ])("does not flag %s", (source) => {
    expect(scanSource(source, "fixture.ts")).toEqual([]);
  });
});
