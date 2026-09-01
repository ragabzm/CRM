/**
 * Local ESLint rules for the design-system floor (Story 1.2, work item 493).
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

const designSystemPlugin = {
  rules: {
    "logical-utilities-only": logicalUtilitiesOnly,
    "semantic-tokens-only": semanticTokensOnly,
  },
};

export default designSystemPlugin;
