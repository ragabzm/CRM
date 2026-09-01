/**
 * Local ESLint rules for the design-system floor.
 *
 * Story 1.2 (work item 493) — tokens and logical utilities.
 * Story 1.3 (work item 494) — externalised strings and one formatting layer.
 * Story 1.4 (work item 495) — the three responsive bands.
 *
 * These are written as real rules rather than `no-restricted-syntax` regexes
 * because both checks need to reason about *class tokens*, not raw source text.
 * A regex over the whole string cannot tell `ml-2` (a physical margin, banned)
 * from `slide-in-from-left-2` (an animation origin keyed to Radix's own
 * physical side, fine) without producing false positives that teach people to
 * disable the rule.
 */

/** Physical layout utilities. The bare utility, after variants are stripped. */
const PHYSICAL_UTILITY = /^(?:ml|mr|pl|pr|left|right)-/;
const PHYSICAL_TEXT_ALIGN = /^text-(?:left|right)$/;

/** Primitive palette scales declared in tokens.css for Tailwind's benefit. */
const PRIMITIVE_SCALES = "n|status|sla|priority|channel|cat|ord|chart|ai";
const PRIMITIVE_UTILITY = new RegExp(
  `^(?:bg|text|border|ring|fill|stroke|outline|shadow|from|to|via|divide)-(?:${PRIMITIVE_SCALES})-`,
);

/** Raw colour values of any syntax. */
const COLOUR_LITERAL = /#[0-9a-fA-F]{3,8}\b|\brgba?\(|\boklch\(|\bhsla?\(|\bcolor-mix\(/;

const PHYSICAL_MESSAGE =
  "Use logical utilities (ms-/me-/ps-/pe-/start-/end-/text-start/text-end) — physical utilities break RTL.";

const TOKEN_MESSAGE =
  "Components must reference semantic tokens (bg-surface-raised, text-fg-muted) — never primitives or literals.";

/** Functions whose string arguments are class names. */
const CLASS_CALLEES = new Set(["cn", "clsx", "classNames", "cva", "twMerge", "tw"]);

/**
 * Names that declare a class list, e.g. `const SHEET_CONTENT_CLASSES = "..."`.
 *
 * Without this, hoisting a long class string into a constant would take it out
 * of every class context and silently exempt it from both rules — which is
 * exactly the refactor someone reaches for when a line gets long.
 */
const CLASS_VARIABLE_NAME = /(?:^|_)(?:CLASS|CLASSES)$|(?:[Cc]lass(?:Name)?e?s?)$/;

/**
 * Is this string node a class name?
 *
 * True inside a className/class JSX attribute, or inside a call to one of the
 * class-composition helpers. Anything else is prose and must not be linted for
 * utilities — otherwise a sentence containing "right-" trips the rule.
 */
function isClassContext(node, sourceCode) {
  let ancestors;

  try {
    ancestors = sourceCode.getAncestors(node);
  } catch {
    ancestors = [];
  }

  for (let i = ancestors.length - 1; i >= 0; i--) {
    const ancestor = ancestors[i];

    if (
      ancestor.type === "JSXAttribute" &&
      ancestor.name &&
      (ancestor.name.name === "className" || ancestor.name.name === "class")
    ) {
      return true;
    }

    if (
      ancestor.type === "VariableDeclarator" &&
      ancestor.id.type === "Identifier" &&
      CLASS_VARIABLE_NAME.test(ancestor.id.name)
    ) {
      return true;
    }

    if (ancestor.type === "CallExpression") {
      const callee = ancestor.callee;
      const name =
        callee.type === "Identifier"
          ? callee.name
          : callee.type === "MemberExpression" && callee.property.type === "Identifier"
            ? callee.property.name
            : null;
      if (name && CLASS_CALLEES.has(name)) return true;
    }
  }

  return false;
}

/** Split a class string into bare utilities, discarding variant prefixes. */
function bareUtilities(value) {
  return value
    .split(/\s+/)
    .filter(Boolean)
    .map((token) => {
      // Strip variant prefixes: `md:hover:ml-2` -> `ml-2`. Colons inside
      // arbitrary values (`data-[side=left]:left-0`) are handled by taking the
      // segment after the last colon that is NOT inside brackets.
      let depth = 0;
      let lastColon = -1;
      for (let i = 0; i < token.length; i++) {
        const ch = token[i];
        if (ch === "[" || ch === "(") depth++;
        else if (ch === "]" || ch === ")") depth--;
        else if (ch === ":" && depth === 0) lastColon = i;
      }
      return lastColon === -1 ? token : token.slice(lastColon + 1);
    })
    .map((token) => token.replace(/^[!-]+/, "")); // strip `!` important and `-` negation
}

function checkPhysical(context, node, value) {
  for (const utility of bareUtilities(value)) {
    if (PHYSICAL_UTILITY.test(utility) || PHYSICAL_TEXT_ALIGN.test(utility)) {
      context.report({ node, message: `${PHYSICAL_MESSAGE} Found: "${utility}".` });
      return;
    }
  }
}

function checkTokens(context, node, value) {
  for (const utility of bareUtilities(value)) {
    if (PRIMITIVE_UTILITY.test(utility)) {
      context.report({ node, message: `${TOKEN_MESSAGE} Found: "${utility}".` });
      return;
    }
  }
}

/** Builds a rule that inspects every string/template chunk in a class context. */
function classStringRule(check, description) {
  return {
    meta: { type: "problem", docs: { description }, schema: [] },
    create(context) {
      const sourceCode = context.sourceCode ?? context.getSourceCode();

      return {
        Literal(node) {
          if (typeof node.value !== "string") return;
          if (!isClassContext(node, sourceCode)) return;
          check(context, node, node.value);
        },
        TemplateElement(node) {
          if (!isClassContext(node, sourceCode)) return;
          check(context, node, node.value.raw);
        },
      };
    },
  };
}

const logicalUtilitiesOnly = classStringRule(
  checkPhysical,
  "Ban physical direction utilities so RTL cannot silently break.",
);

const semanticTokensOnly = {
  meta: {
    type: "problem",
    docs: { description: "Ban primitive palette names and raw colour literals in components." },
    schema: [],
  },
  create(context) {
    const sourceCode = context.sourceCode ?? context.getSourceCode();

    function reportLiteralColour(node, value) {
      if (COLOUR_LITERAL.test(value)) {
        context.report({ node, message: `${TOKEN_MESSAGE} Found a raw colour value.` });
        return true;
      }
      return false;
    }

    return {
      Literal(node) {
        if (typeof node.value !== "string") return;

        // A raw colour is banned anywhere in a component — className, a style
        // object, an SVG fill prop, a constant. There is no context in which a
        // component should be naming its own colour.
        if (reportLiteralColour(node, node.value)) return;

        if (!isClassContext(node, sourceCode)) return;
        checkTokens(context, node, node.value);
      },
      TemplateElement(node) {
        if (reportLiteralColour(node, node.value.raw)) return;
        if (!isClassContext(node, sourceCode)) return;
        checkTokens(context, node, node.value.raw);
      },
    };
  },
};


/* ------------------------------------------------------------------------- *
 * Story 1.3 — i18n rules
 * ------------------------------------------------------------------------- */

/** Any letter in any script. A run with no letters is punctuation or digits. */
const CONTAINS_LETTER = /\p{L}/u;

/** Elements whose text is not prose. */
const NON_PROSE_ELEMENTS = new Set(["script", "style"]);

/** Opt-out for the rare genuinely-untranslatable run (a brand mark, a code). */
const I18N_IGNORE_ATTRIBUTE = "data-i18n-ignore";

const LITERAL_JSX_MESSAGE =
  "String literal in JSX — externalise into messages/{en,ar}.json and use useTranslations().";

function elementName(node) {
  const opening = node?.openingElement;
  return opening?.name?.type === "JSXIdentifier" ? opening.name.name : null;
}

function hasI18nIgnore(node) {
  const attributes = node?.openingElement?.attributes ?? [];
  return attributes.some(
    (attribute) =>
      attribute.type === "JSXAttribute" && attribute.name?.name === I18N_IGNORE_ATTRIBUTE,
  );
}

/** True when any JSX ancestor opts out or is a non-prose element. */
function isExemptByAncestor(ancestors) {
  for (let i = ancestors.length - 1; i >= 0; i--) {
    const ancestor = ancestors[i];
    if (ancestor.type !== "JSXElement") continue;

    const name = elementName(ancestor);
    if (name && NON_PROSE_ELEMENTS.has(name.toLowerCase())) return true;
    if (hasI18nIgnore(ancestor)) return true;
  }

  return false;
}

/**
 * Bans hard-coded user-facing text in JSX children.
 *
 * A literal that ships is a string Arabic readers will never see translated, and
 * it is invisible in review because it looks like ordinary markup. The rule
 * targets *children* only: attributes carry class names and ids far more often
 * than prose, and flagging those would produce noise that gets the rule
 * disabled. (Translating user-facing attributes such as aria-label is a real
 * gap, tracked separately.)
 */
const noLiteralJsxStrings = {
  meta: {
    type: "problem",
    docs: { description: "Require user-facing JSX text to come from the message catalogue." },
    schema: [],
  },
  create(context) {
    const sourceCode = context.sourceCode ?? context.getSourceCode();

    function check(node, rawValue) {
      if (!CONTAINS_LETTER.test(rawValue)) return;
      if (isExemptByAncestor(sourceCode.getAncestors(node))) return;

      context.report({ node, message: LITERAL_JSX_MESSAGE });
    }

    return {
      JSXText(node) {
        check(node, node.value);
      },
      // `<span>{"Ragab CRM"}</span>` is the same mistake wearing braces.
      Literal(node) {
        if (typeof node.value !== "string") return;
        if (node.parent?.type !== "JSXExpressionContainer") return;
        if (node.parent.parent?.type !== "JSXElement" && node.parent.parent?.type !== "JSXFragment") {
          return;
        }

        check(node, node.value);
      },
    };
  },
};

/** Intl constructors that carry locale-dependent formatting decisions. */
const INTL_CONSTRUCTORS = new Set([
  "DateTimeFormat",
  "NumberFormat",
  "RelativeTimeFormat",
  "ListFormat",
  "PluralRules",
  "Collator",
]);

/** Instance methods that quietly format against the ambient locale. */
const TO_LOCALE_METHODS = new Set([
  "toLocaleString",
  "toLocaleDateString",
  "toLocaleTimeString",
]);

const DIRECT_INTL_MESSAGE = "Direct Intl / toLocale* call — go through lib/format/.";

/**
 * Keeps every locale-dependent formatting decision in one module.
 *
 * `Intl` defaults vary by locale AND by the runtime's ICU build: bare "ar"
 * yields Hijri dates and Eastern Arabic digits on some platforms. One component
 * calling Intl directly is one surface that silently disagrees with the rest of
 * the product, in a language most reviewers do not read.
 */
const noDirectIntlFormatting = {
  meta: {
    type: "problem",
    docs: { description: "Route all locale-dependent formatting through lib/format/." },
    schema: [],
  },
  create(context) {
    return {
      MemberExpression(node) {
        if (
          node.object?.type === "Identifier" &&
          node.object.name === "Intl" &&
          node.property?.type === "Identifier" &&
          INTL_CONSTRUCTORS.has(node.property.name)
        ) {
          context.report({ node, message: DIRECT_INTL_MESSAGE });
        }
      },
      CallExpression(node) {
        const callee = node.callee;
        if (
          callee?.type === "MemberExpression" &&
          callee.property?.type === "Identifier" &&
          TO_LOCALE_METHODS.has(callee.property.name)
        ) {
          context.report({ node, message: DIRECT_INTL_MESSAGE });
        }
      },
    };
  },
};


/* ------------------------------------------------------------------------- *
 * Story 1.4 — responsive bands
 * ------------------------------------------------------------------------- */

/** Tailwind's default screens. Deleted from the theme; banned from source. */
const BARE_BREAKPOINTS = /^(?:sm|md|lg|xl|2xl)$/;

/** A hand-rolled media query, in a template literal or a style string. */
const RAW_MEDIA_QUERY = /@media\s*[(\w]/;

const BREAKPOINT_MESSAGE =
  "Use a named band (mobile:/tablet:/desktop:) — bare Tailwind screens are not the product's breakpoints.";

const RAW_MEDIA_MESSAGE =
  "Hand-rolled @media query — use a named band (mobile:/tablet:/desktop:).";

/**
 * Keeps the product to exactly three breakpoints.
 *
 * Three bands is a product decision, not a styling preference: each one is a
 * posture — a thumb, a finger, a pointer — and a fourth ad-hoc breakpoint means
 * a layout that was designed for nobody. `--breakpoint-*: initial` in tokens.css
 * already makes a bare `md:` generate no CSS at all; this rule is what stops it
 * being written in the first place, when the author would otherwise see their
 * class silently do nothing.
 *
 * Variant chains are parsed rather than pattern-matched, so `data-[side=x]:md:`
 * is caught while a Button size key named `md` is not.
 */
const noAdhocBreakpoint = {
  meta: {
    type: "problem",
    docs: { description: "Restrict breakpoints to the three named responsive bands." },
    schema: [],
  },
  create(context) {
    const sourceCode = context.sourceCode ?? context.getSourceCode();

    function checkClasses(node, value) {
      for (const token of value.split(/\s+/).filter(Boolean)) {
        // Every colon-separated segment before the utility is a variant.
        let depth = 0;
        let segment = "";
        for (let i = 0; i < token.length; i++) {
          const ch = token[i];
          if (ch === "[" || ch === "(") depth++;
          else if (ch === "]" || ch === ")") depth--;

          if (ch === ":" && depth === 0) {
            if (BARE_BREAKPOINTS.test(segment)) {
              context.report({ node, message: `${BREAKPOINT_MESSAGE} Found: "${segment}:".` });
              return;
            }
            segment = "";
          } else {
            segment += ch;
          }
        }
      }
    }

    function checkRawMedia(node, value) {
      if (RAW_MEDIA_QUERY.test(value)) {
        context.report({ node, message: RAW_MEDIA_MESSAGE });
        return true;
      }
      return false;
    }

    return {
      Literal(node) {
        if (typeof node.value !== "string") return;
        if (checkRawMedia(node, node.value)) return;
        if (!isClassContext(node, sourceCode)) return;
        checkClasses(node, node.value);
      },
      TemplateElement(node) {
        if (checkRawMedia(node, node.value.raw)) return;
        if (!isClassContext(node, sourceCode)) return;
        checkClasses(node, node.value.raw);
      },
    };
  },
};

const designSystemPlugin = {
  rules: {
    "logical-utilities-only": logicalUtilitiesOnly,
    "semantic-tokens-only": semanticTokensOnly,
    "no-literal-jsx-strings": noLiteralJsxStrings,
    "no-direct-intl-formatting": noDirectIntlFormatting,
    "no-adhoc-breakpoint": noAdhocBreakpoint,
  },
};

export default designSystemPlugin;
