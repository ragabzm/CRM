import { describe, expect, it } from "vitest";

import {
  FORBIDDEN_VERSIONS,
  MINIMUM_VERSION,
  resolveNextVersion,
  validate,
} from "@/scripts/check-next-version.mjs";

describe("Next.js version pin", () => {
  it("resolves the installed version", () => {
    expect(resolveNextVersion()).toBe(MINIMUM_VERSION);
  });

  it("accepts the installed version", () => {
    expect(validate(resolveNextVersion())).toEqual([]);
  });

  it("rejects 16.3.2 explicitly", () => {
    expect(FORBIDDEN_VERSIONS).toContain("16.3.2");
    expect(validate("16.3.2")).not.toEqual([]);
  });

  it.each(["16.3.0", "16.3.1", "16.2.9", "15.5.0"])("rejects the older release %s", (version) => {
    expect(validate(version)).not.toEqual([]);
  });

  it("rejects a version outside the 16.3.x line", () => {
    expect(validate("16.4.0")).not.toEqual([]);
  });
});
