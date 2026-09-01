import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

import designSystem from "./eslint-rules/index.mjs";

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,

  {
    plugins: { "design-system": designSystem },
  },

  {
    /*
     * Story 1.1: the frontend is separately deployable, which stops being true
     * the moment it can read the backend's source.
     */
    rules: {
      "no-restricted-imports": [
        "error",
        {
          patterns: [
            {
              group: ["**/backend/*", "../backend", "../backend/**", "../../backend/**"],
              message:
                "frontend/ must not import from backend/. Use the generated client in lib/api/ instead.",
            },
          ],
        },
      ],
    },
  },

  {
    /*
     * RULE 1 — physical utilities are lint errors everywhere a class name is
     * written, not only under components/. A layout or a screen with `ml-4`
     * breaks Arabic exactly as badly as a primitive does.
     */
    files: ["app/**/*.{ts,tsx}", "components/**/*.{ts,tsx}", "lib/**/*.{ts,tsx}"],
    rules: {
      "design-system/logical-utilities-only": "error",
    },
  },

  {
    /*
     * RULE 2 — primitives and raw colour literals are banned inside components/.
     * The primitive scales exist in tokens.css so Tailwind can generate
     * utilities from them; the ban is on a *component* reaching past the
     * semantic layer to grab one.
     */
    files: ["components/**/*.{ts,tsx}"],
    rules: {
      "design-system/semantic-tokens-only": "error",
    },
  },

  {
    /*
     * RULE 3 — layer separation. A screen assembled from repeated Layer-A
     * primitives is how six epics end up with six different badges. If a screen
     * needs a primitive directly, that is the signal a domain component is
     * missing, not a reason to import the primitive.
     */
    files: ["components/screens/**/*.{ts,tsx}"],
    rules: {
      "no-restricted-imports": [
        "error",
        {
          patterns: [
            {
              group: [
                "@/components/ui",
                "@/components/ui/*",
                "../ui/*",
                "../../ui/*",
                "**/components/ui/*",
              ],
              message:
                "Screens must compose domain components; a screen assembled from repeated Layer-A primitives fails review.",
            },
            {
              group: ["**/backend/*", "../backend", "../backend/**", "../../backend/**"],
              message: "frontend/ must not import from backend/.",
            },
          ],
        },
      ],
    },
  },

  {
    /*
     * RULE 4 — no hard-coded user-facing text. A literal that ships is a string
     * Arabic readers will never see translated, and it is invisible in review
     * because it looks like ordinary markup.
     */
    files: ["app/**/*.{ts,tsx}", "components/**/*.{ts,tsx}"],
    ignores: ["**/*.test.{ts,tsx}", "**/__tests__/**", "**/*.stories.tsx"],
    rules: {
      "design-system/no-literal-jsx-strings": "error",
    },
  },

  {
    /*
     * RULE 5 — one formatting layer. lib/format/ is exempt because it IS the
     * layer; everywhere else, a direct Intl call is a surface that can silently
     * disagree with the rest of the product in a language most reviewers do not
     * read.
     */
    files: ["app/**/*.{ts,tsx}", "components/**/*.{ts,tsx}", "lib/**/*.{ts,tsx}"],
    ignores: ["lib/format/**", "**/*.test.{ts,tsx}", "**/__tests__/**"],
    rules: {
      "design-system/no-direct-intl-formatting": "error",
    },
  },

  {
    /*
     * RULE 6 — exactly three breakpoints. Each band is a posture (a thumb, a
     * finger, a pointer); a fourth ad-hoc breakpoint is a layout designed for
     * nobody. Applies to lib/ and app/ too, not only components/.
     */
    files: ["app/**/*.{ts,tsx}", "components/**/*.{ts,tsx}", "lib/**/*.{ts,tsx}"],
    ignores: ["**/*.test.{ts,tsx}", "**/__tests__/**"],
    rules: {
      "design-system/no-adhoc-breakpoint": "error",
    },
  },

  globalIgnores([".next/**", "out/**", "build/**", "coverage/**", "next-env.d.ts"]),
]);

export default eslintConfig;
