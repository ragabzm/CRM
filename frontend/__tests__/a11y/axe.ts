import { configureAxe } from "jest-axe";

/**
 * The accessibility gate.
 *
 * Scoped to WCAG 2.1 A and AA, which is the standard the product commits to.
 * Nothing is disabled globally: a rule that is switched off here is a class of
 * defect nobody will ever be told about again, so exceptions belong at the
 * individual assertion with a reason.
 */
export const axe = configureAxe({
  rules: {
    /*
     * `region` requires every piece of content to sit inside a landmark. That
     * is a page-level property, and most of these fixtures are single
     * components rendered into a bare div — so it is disabled for COMPONENT
     * fixtures only. The page suite renders inside <main> and keeps it on.
     */
    region: { enabled: false },
  },
});

/** Page-level axe: every rule, including landmark structure. */
export const axePage = configureAxe({});

export const WCAG_TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"];
