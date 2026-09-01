import { INTL_TAG, type Locale } from "@/lib/i18n/locale";

/**
 * The one formatting layer.
 *
 * Every date, number, currency and duration in the product is formatted here
 * and nowhere else. `design-system/no-direct-intl-formatting` makes a direct
 * `Intl.*` or `toLocaleString` call outside this folder a lint error.
 *
 * The reason is not tidiness. `Intl` defaults differ per locale and per ICU
 * build: bare `ar` yields Hijri dates and Eastern Arabic digits on some
 * runtimes and not others. Centralising means the product's answer to "what
 * calendar, which digits" is written once, in `INTL_TAG`, and cannot drift one
 * component at a time.
 */

/** Thrown when a caller hands a formatter something that is not a date/number. */
export class FormatError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "FormatError";
  }
}

/*
 * Intl constructors are expensive relative to formatting, and lists re-render
 * often. Cached per (tag, options); the key is the serialised options object,
 * which is stable because callers pass literals.
 */
const dateTimeFormatters = new Map<string, Intl.DateTimeFormat>();
const numberFormatters = new Map<string, Intl.NumberFormat>();
const relativeTimeFormatters = new Map<string, Intl.RelativeTimeFormat>();

function dateTimeFormatter(
  locale: Locale,
  options: Intl.DateTimeFormatOptions,
): Intl.DateTimeFormat {
  const tag = INTL_TAG[locale];
  const key = `${tag}|${JSON.stringify(options)}`;

  let formatter = dateTimeFormatters.get(key);
  if (!formatter) {
    formatter = new Intl.DateTimeFormat(tag, options);
    dateTimeFormatters.set(key, formatter);
  }

  return formatter;
}

function numberFormatter(locale: Locale, options: Intl.NumberFormatOptions): Intl.NumberFormat {
  const tag = INTL_TAG[locale];
  const key = `${tag}|${JSON.stringify(options)}`;

  let formatter = numberFormatters.get(key);
  if (!formatter) {
    formatter = new Intl.NumberFormat(tag, options);
    numberFormatters.set(key, formatter);
  }

  return formatter;
}

function relativeTimeFormatter(locale: Locale): Intl.RelativeTimeFormat {
  const tag = INTL_TAG[locale];

  let formatter = relativeTimeFormatters.get(tag);
  if (!formatter) {
    formatter = new Intl.RelativeTimeFormat(tag, { numeric: "auto" });
    relativeTimeFormatters.set(tag, formatter);
  }

  return formatter;
}

/** Coerces at the boundary and refuses invalid input loudly. */
function toDate(value: Date | string | number, label: string): Date {
  const date = value instanceof Date ? value : new Date(value);

  if (Number.isNaN(date.getTime())) {
    throw new FormatError(`${label} received an invalid date: ${String(value)}`);
  }

  return date;
}

function assertFiniteNumber(value: number, label: string): number {
  if (typeof value !== "number" || !Number.isFinite(value)) {
    throw new FormatError(`${label} received an invalid number: ${String(value)}`);
  }

  return value;
}

/** e.g. "5 Jan 2024". Gregorian and Western digits in both locales. */
export function formatDate(value: Date | string | number, locale: Locale): string {
  return dateTimeFormatter(locale, {
    year: "numeric",
    month: "short",
    day: "numeric",
  }).format(toDate(value, "formatDate"));
}

/** e.g. "5 Jan 2024, 14:30". */
export function formatDateTime(value: Date | string | number, locale: Locale): string {
  return dateTimeFormatter(locale, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(toDate(value, "formatDateTime"));
}

/** e.g. "14:30". */
export function formatTime(value: Date | string | number, locale: Locale): string {
  return dateTimeFormatter(locale, { timeStyle: "short" }).format(toDate(value, "formatTime"));
}

export function formatNumber(
  value: number,
  locale: Locale,
  options: Intl.NumberFormatOptions = {},
): string {
  return numberFormatter(locale, options).format(assertFiniteNumber(value, "formatNumber"));
}

export function formatCurrency(value: number, currency: string, locale: Locale): string {
  return numberFormatter(locale, {
    style: "currency",
    currency,
  }).format(assertFiniteNumber(value, "formatCurrency"));
}

/** Largest sensible unit: "3 days ago", "in 2 hours". */
const RELATIVE_UNITS: Array<[Intl.RelativeTimeFormatUnit, number]> = [
  ["year", 1000 * 60 * 60 * 24 * 365],
  ["month", 1000 * 60 * 60 * 24 * 30],
  ["week", 1000 * 60 * 60 * 24 * 7],
  ["day", 1000 * 60 * 60 * 24],
  ["hour", 1000 * 60 * 60],
  ["minute", 1000 * 60],
  ["second", 1000],
];

export function formatRelativeTime(
  from: Date | string | number,
  to: Date | string | number,
  locale: Locale,
): string {
  const fromDate = toDate(from, "formatRelativeTime");
  const toDate_ = toDate(to, "formatRelativeTime");
  const deltaMs = fromDate.getTime() - toDate_.getTime();

  for (const [unit, ms] of RELATIVE_UNITS) {
    if (Math.abs(deltaMs) >= ms) {
      return relativeTimeFormatter(locale).format(Math.trunc(deltaMs / ms), unit);
    }
  }

  return relativeTimeFormatter(locale).format(0, "second");
}

const FILE_SIZE_UNITS = ["B", "KB", "MB", "GB", "TB"] as const;

/**
 * Binary units with a decimal-ish label, which is what every file manager the
 * user has met shows. The unit is not translated: KB/MB are read as symbols.
 */
export function formatFileSize(bytes: number, locale: Locale): string {
  assertFiniteNumber(bytes, "formatFileSize");

  if (bytes < 0) {
    throw new FormatError(`formatFileSize received a negative size: ${bytes}`);
  }

  let size = bytes;
  let unitIndex = 0;

  while (size >= 1024 && unitIndex < FILE_SIZE_UNITS.length - 1) {
    size /= 1024;
    unitIndex++;
  }

  const formatted = formatNumber(size, locale, {
    maximumFractionDigits: unitIndex === 0 ? 0 : 1,
  });

  return `${formatted} ${FILE_SIZE_UNITS[unitIndex]}`;
}
