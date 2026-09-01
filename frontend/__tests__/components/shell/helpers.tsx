/**
 * Shell tests share the project-wide intl helpers; this file exists only so the
 * shell suites can import from a local path. One provider definition, one set of
 * message fixtures.
 */
export { ar, en, render, screen, waitFor, withIntl } from "@/__tests__/helpers/intl";
