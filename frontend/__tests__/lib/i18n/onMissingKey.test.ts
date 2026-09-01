import { afterEach, describe, expect, it, vi } from "vitest";

import { extractKey, onMissingKey, translationReporter } from "@/lib/i18n/onMissingKey";


function missingMessageError(key: string, locale: string) {
  return {
    code: "MISSING_MESSAGE",
    message: `Could not resolve \`${key}\` in messages for locale \`${locale}\`.`,
  };
}

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllEnvs();
});

describe("extractKey", () => {
  it("pulls the backticked key out of next-intl's message", () => {
    expect(extractKey(missingMessageError("shell.nav.home", "ar"))).toBe("shell.nav.home");
  });

  it("falls back to the whole message rather than dropping the report", () => {
    expect(extractKey({ message: "something unexpected" })).toBe("something unexpected");
  });

  it("survives a non-error value", () => {
    expect(extractKey(undefined)).toBe("");
  });
});

describe("onMissingKey", () => {
  it("warns in development", () => {
    vi.stubEnv("NODE_ENV", "development");
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});

    onMissingKey(missingMessageError("shell.brand", "ar"), "ar");

    expect(warn).toHaveBeenCalledOnce();
    expect(warn.mock.calls[0]![0]).toContain("shell.brand");
  });

  it("reports the key and locale to the logger sink", () => {
    vi.stubEnv("NODE_ENV", "production");
    const sink = vi.spyOn(translationReporter, "report");

    onMissingKey(missingMessageError("home.title", "ar"), "ar");

    // The string still renders (next-intl falls back to English), so nothing
    // looks broken on screen — which is exactly why it has to be reported.
    expect(sink).toHaveBeenCalledWith({ key: "home.title", locale: "ar" });
  });

  it("does not warn in production", () => {
    vi.stubEnv("NODE_ENV", "production");
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});

    onMissingKey(missingMessageError("home.title", "ar"), "ar");

    expect(warn).not.toHaveBeenCalled();
  });

  it("rethrows a non-missing-key error in development", () => {
    vi.stubEnv("NODE_ENV", "development");

    // Malformed ICU syntax is a defect, not a content gap. Filing it as a
    // missing translation would bury it.
    expect(() => onMissingKey({ code: "INVALID_MESSAGE", message: "bad ICU" }, "en")).toThrow();
  });

  it("swallows a non-missing-key error in production", () => {
    vi.stubEnv("NODE_ENV", "production");

    expect(() => onMissingKey({ code: "INVALID_MESSAGE", message: "bad ICU" }, "en")).not.toThrow();
  });

  it("records an unknown locale rather than losing the report", () => {
    vi.stubEnv("NODE_ENV", "production");
    const sink = vi.spyOn(translationReporter, "report");

    onMissingKey(missingMessageError("shell.brand", "ar"));

    expect(sink).toHaveBeenCalledWith({ key: "shell.brand", locale: "unknown" });
  });
});
