import { readFileSync } from "node:fs";
import { join } from "node:path";

import { describe, expect, it } from "vitest";

import {
  PRIMITIVE_PREFIXES,
  SEMANTIC_TOKENS,
  semanticVar,
  type SemanticToken,
} from "@/tokens/tokens";

const tokensCss = readFileSync(join(process.cwd(), "tokens/tokens.css"), "utf8");

/** Custom properties declared anywhere in tokens.css. */
const declared = new Set(
  [...tokensCss.matchAll(/^\s*(--[a-z0-9-]+)\s*:/gm)].map((match) => match[1]),
);

describe("semantic token vocabulary", () => {
  it("declares every TypeScript token in CSS", () => {
    const missing = SEMANTIC_TOKENS.filter((token) => !declared.has(`--${token}`));

    expect(missing).toEqual([]);
  });

  it("builds a var() reference", () => {
    expect(semanticVar("surface-raised")).toBe("var(--surface-raised)");
    expect(semanticVar("fg-muted")).toBe("var(--fg-muted)");
  });

  it("has no duplicate names", () => {
    expect(new Set(SEMANTIC_TOKENS).size).toBe(SEMANTIC_TOKENS.length);
  });

  it.each([
    "surface-base",
    "surface-raised",
    "surface-sunken",
    "fg-default",
    "fg-muted",
    "fg-inverse",
    "border-default",
    "border-strong",
    "focus-ring",
    "state-success",
    "state-warning",
    "state-danger",
    "state-info",
  ] satisfies SemanticToken[])("exposes the required alias %s", (token) => {
    expect(SEMANTIC_TOKENS).toContain(token);
  });
});

describe("token layer structure", () => {
  it("maps every semantic token to a Tailwind utility or is a non-colour token", () => {
    // Colour-ish semantics must be reachable as utilities (bg-surface-raised);
    // measures and shadows are consumed as var() directly.
    const utilityMapped = new Set(
      [...tokensCss.matchAll(/--color-([a-z0-9-]+):\s*var\(--([a-z0-9-]+)\)/g)].map((m) => m[2]),
    );

    const needsUtility = SEMANTIC_TOKENS.filter(
      (token) =>
        token.startsWith("surface-") ||
        token.startsWith("fg-") ||
        token.startsWith("border-") ||
        token.startsWith("accent-") ||
        token.startsWith("state-"),
    ).filter((token) => token !== "border-inverse" && token !== "accent-fg");

    const unmapped = needsUtility.filter((token) => !utilityMapped.has(token));

    expect(unmapped).toEqual([]);
  });

  it("keeps the dark theme hook present so it can be populated without re-plumbing", () => {
    expect(tokensCss).toContain('[data-theme="dark"]');
  });

  it("declares the primitive scales the lint rules key off", () => {
    for (const prefix of PRIMITIVE_PREFIXES) {
      expect(tokensCss).toContain(`--color-${prefix}`);
    }
  });

  it("carries the elevation and radius values from the design source", () => {
    expect(declared.has("--radius-sm")).toBe(true);
    expect(declared.has("--radius-pill")).toBe(true);
    expect(declared.has("--shadow-e-dialog")).toBe(true);
    expect(declared.has("--spacing-11")).toBe(true);
  });
});
