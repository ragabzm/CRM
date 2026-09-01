import { toHaveNoViolations } from "jest-axe";
import { expect } from "vitest";

/**
 * Registers the axe matcher for the a11y suites.
 *
 * Kept in its own setup file rather than the global one so the matcher's cost
 * is paid only by the suites that use it.
 */
expect.extend(toHaveNoViolations);
