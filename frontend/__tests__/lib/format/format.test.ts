import { describe, expect, it } from "vitest";

import {
  FormatError,
  formatCurrency,
  formatDate,
  formatDateTime,
  formatFileSize,
  formatNumber,
  formatRelativeTime,
  formatTime,
} from "@/lib/format";
import { INTL_TAG, LOCALES, type Locale } from "@/lib/i18n/locale";

/** Eastern Arabic-Indic digits. Must never appear: UX-05 requires Western 0-9. */
const EASTERN_DIGITS = /[٠-٩۰-۹]/;

/** A run of digits, so we can assert the digits themselves are ASCII. */
const ASCII_DIGIT = /[0-9]/;

const SAMPLE = new Date("2024-01-05T14:30:00Z");

describe("the Intl tag is pinned, not inferred", () => {
  it("never hands a bare locale to Intl", () => {
    expect(INTL_TAG.en).toBe("en-US");
    // Bare "ar" resolves to Hijri dates and Eastern digits on some ICU builds.
    // The extension makes the answer the same on every runtime.
    expect(INTL_TAG.ar).toBe("ar-u-ca-gregory-nu-latn");
  });
});

describe.each(LOCALES)("formatters in %s", (locale: Locale) => {
  const cases: Array<[string, string]> = [
    ["formatDate", formatDate(SAMPLE, locale)],
    ["formatDateTime", formatDateTime(SAMPLE, locale)],
    ["formatTime", formatTime(SAMPLE, locale)],
    ["formatNumber", formatNumber(1_284_567.5, locale)],
    ["formatCurrency", formatCurrency(1284.5, "SAR", locale)],
    ["formatFileSize", formatFileSize(2_621_440, locale)],
  ];

  it.each(cases)("%s emits Western digits only", (_name, output) => {
    expect(output).not.toMatch(EASTERN_DIGITS);
    expect(output).toMatch(ASCII_DIGIT);
  });

  it("uses the Gregorian calendar", () => {
    // 2024 Gregorian is 1445 Hijri; seeing the Gregorian year proves the
    // calendar did not fall back.
    expect(formatDate(SAMPLE, locale)).toContain("2024");
  });

  it("formats the same instant to the same numeric parts in both locales", () => {
    const digitsOnly = (value: string) => value.replace(/[^0-9]/g, "");
    expect(digitsOnly(formatDate(SAMPLE, locale))).toBe(digitsOnly(formatDate(SAMPLE, "en")));
  });
});

describe("date formatting", () => {
  it("renders a readable medium date in English", () => {
    expect(formatDate(SAMPLE, "en")).toBe("Jan 5, 2024");
  });

  it("accepts strings and epoch milliseconds, not just Date", () => {
    expect(formatDate("2024-01-05T14:30:00Z", "en")).toBe(formatDate(SAMPLE, "en"));
    expect(formatDate(SAMPLE.getTime(), "en")).toBe(formatDate(SAMPLE, "en"));
  });
});

describe("number formatting", () => {
  it("groups thousands", () => {
    expect(formatNumber(1_284_567, "en")).toBe("1,284,567");
  });

  it("passes options through", () => {
    expect(formatNumber(0.427, "en", { style: "percent", maximumFractionDigits: 1 })).toBe("42.7%");
  });

  it("renders a currency with its code", () => {
    expect(formatCurrency(1284.5, "SAR", "en")).toMatch(/1,284\.50/);
  });
});

describe("relative time", () => {
  it("picks the largest sensible unit", () => {
    const now = new Date("2024-01-05T00:00:00Z");
    const threeDaysAgo = new Date("2024-01-02T00:00:00Z");

    expect(formatRelativeTime(threeDaysAgo, now, "en")).toBe("3 days ago");
  });

  it("handles the future direction", () => {
    const now = new Date("2024-01-05T00:00:00Z");
    const inTwoHours = new Date("2024-01-05T02:00:00Z");

    expect(formatRelativeTime(inTwoHours, now, "en")).toBe("in 2 hours");
  });

  it("degrades to seconds rather than throwing on a tiny delta", () => {
    const now = new Date("2024-01-05T00:00:00Z");
    expect(formatRelativeTime(now, now, "en")).toBeTruthy();
  });
});

describe("file size", () => {
  it("steps up binary units", () => {
    expect(formatFileSize(512, "en")).toBe("512 B");
    expect(formatFileSize(2_621_440, "en")).toBe("2.5 MB");
  });
});

describe("invalid input fails loudly", () => {
  it.each([
    ["formatDate", () => formatDate(Number.NaN, "en")],
    ["formatDate with rubbish", () => formatDate("not-a-date", "en")],
    ["formatDateTime", () => formatDateTime(Number.NaN, "en")],
    ["formatNumber", () => formatNumber(Number.NaN, "en")],
    ["formatCurrency", () => formatCurrency(Number.POSITIVE_INFINITY, "SAR", "en")],
    ["formatFileSize negative", () => formatFileSize(-1, "en")],
  ])("%s throws FormatError", (_name, run) => {
    // Silently rendering "Invalid Date" into a table is worse than failing at
    // the call site where the bad value came from.
    expect(run).toThrow(FormatError);
  });
});
