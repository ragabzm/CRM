import { beforeAll, describe, expect, it } from "vitest";

import { lintFixture, messagesFrom } from "./helpers";

/*
 * All three lint rules are exercised in ONE file on purpose.
 *
 * Vitest isolates workers per test file, so three separate files meant three
 * parallel resolutions of eslint.config.mjs (which pulls in eslint-config-next).
 * That was slow enough to blow the test timeout intermittently — a flaky suite
 * that fails only under load is worse than a slow one. Sharing a file shares the
 * single memoised ESLint instance in ./helpers.
 *
 * The three rule groups keep their own describe blocks below.
 */

/*
 * Pay the one-off cost up front.
 *
 * The first lintFixture call resolves eslint.config.mjs and eslint-config-next,
 * which on a cold cache takes long enough to trip a per-test timeout. Doing it
 * in a hook with its own budget means a genuine rule regression fails fast and
 * loudly, instead of being indistinguishable from a slow machine.
 */
beforeAll(async () => {
  await lintFixture("components/warmup.tsx", "export const W = () => null;\n");
}, 120_000);

const PHYSICAL_RULE = "design-system/logical-utilities-only";

describe("physical-utility ban", () => {
  it("fails on ml- and text-left", async () => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `export const F = () => <div className="ml-4 text-left">x</div>;`,
    );

    const errors = messagesFrom(messages, PHYSICAL_RULE);
    expect(errors.length).toBeGreaterThan(0);
    expect(errors[0]!.message).toMatch(/logical utilities/i);
    expect(errors[0]!.severity).toBe(2);
  });

  it.each(["ml-2", "mr-2", "pl-3", "pr-3", "left-0", "right-0", "text-left", "text-right"])(
    "fails on %s",
    async (utility) => {
      const messages = await lintFixture(
        "components/domain/Fixture.tsx",
        `export const F = () => <div className="${utility}">x</div>;`,
      );

      expect(messagesFrom(messages, PHYSICAL_RULE).length).toBeGreaterThan(0);
    },
  );

  it("fails on a physical utility hidden behind variants", async () => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `export const F = () => <div className="md:hover:ml-4">x</div>;`,
    );

    expect(messagesFrom(messages, PHYSICAL_RULE).length).toBeGreaterThan(0);
  });

  it("fails on a physical utility hoisted into a constant", async () => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `const FIXTURE_CLASSES = "ml-4"; export const F = () => <div className={FIXTURE_CLASSES}>x</div>;`,
    );

    expect(messagesFrom(messages, PHYSICAL_RULE).length).toBeGreaterThan(0);
  });

  it.each(["ms-4", "me-4", "ps-3", "pe-3", "start-0", "end-0", "text-start", "text-end"])(
    "accepts the logical form %s",
    async (utility) => {
      const messages = await lintFixture(
        "components/domain/Fixture.tsx",
        `export const F = () => <div className="${utility}">x</div>;`,
      );

      expect(messagesFrom(messages, PHYSICAL_RULE)).toEqual([]);
    },
  );

  it("does not misfire on animation origins keyed to Radix's physical side", async () => {
    // `slide-in-from-left-2` is an animation origin, not a layout utility, and
    // Radix's data-[side=…] is a physical placement contract. Flagging these
    // would train people to disable the rule.
    const messages = await lintFixture(
      "components/ui/Fixture.tsx",
      `export const F = () => <div className="data-[side=left]:slide-in-from-right-2 slide-out-to-left-10">x</div>;`,
    );

    expect(messagesFrom(messages, PHYSICAL_RULE)).toEqual([]);
  });

  it("does not misfire on prose containing the word right", async () => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `export const F = () => <p>You do not have the right-of-access to this data.</p>;`,
    );

    expect(messagesFrom(messages, PHYSICAL_RULE)).toEqual([]);
  });

  it("applies to app/ as well as components/", async () => {
    const messages = await lintFixture(
      "app/page.tsx",
      `export default function P() { return <div className="ml-2">x</div>; }`,
    );

    expect(messagesFrom(messages, PHYSICAL_RULE).length).toBeGreaterThan(0);
  });
});

const TOKEN_RULE = "design-system/semantic-tokens-only";

describe("primitive and colour-literal ban", () => {
  it("fails on a primitive palette class", async () => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `export const F = () => <div className="bg-n-800">x</div>;`,
    );

    const errors = messagesFrom(messages, TOKEN_RULE);
    expect(errors.length).toBeGreaterThan(0);
    expect(errors[0]!.message).toMatch(/semantic tokens/i);
  });

  it("fails on an inline hex literal in a style attribute", async () => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `export const F = () => <div style={{ color: "#123456" }}>x</div>;`,
    );

    expect(messagesFrom(messages, TOKEN_RULE).length).toBeGreaterThan(0);
  });

  it.each([
    "bg-n-800",
    "text-n-600",
    "border-n-200",
    "bg-status-new-bg",
    "text-sla-breached-fg",
    "bg-priority-urgent",
    "text-channel-email",
    "fill-cat-1",
    "stroke-ord-3",
    "bg-ai-surface",
  ])("fails on the primitive %s", async (utility) => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `export const F = () => <div className="${utility}">x</div>;`,
    );

    expect(messagesFrom(messages, TOKEN_RULE).length).toBeGreaterThan(0);
  });

  it.each(["#fff", "#123456", "rgb(1,2,3)", "rgba(1,2,3,.5)", "oklch(0.5 0 0)", "hsl(1,2%,3%)"])(
    "fails on the raw colour value %s",
    async (value) => {
      const messages = await lintFixture(
        "components/domain/Fixture.tsx",
        `const C = "${value}"; export const F = () => <div>{C}</div>;`,
      );

      expect(messagesFrom(messages, TOKEN_RULE).length).toBeGreaterThan(0);
    },
  );

  it.each([
    "bg-surface-raised",
    "text-fg-muted",
    "border-border-default",
    "bg-accent-default",
    "text-state-danger",
  ])("accepts the semantic token %s", async (utility) => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `export const F = () => <div className="${utility}">x</div>;`,
    );

    expect(messagesFrom(messages, TOKEN_RULE)).toEqual([]);
  });

  it("does not police primitives outside components/", async () => {
    // tokens.css declares the primitives on purpose; app/ is not where the ban
    // lives. Scoping keeps the error where the fix is.
    const messages = await lintFixture(
      "app/page.tsx",
      `export default function P() { return <div className="bg-n-800">x</div>; }`,
    );

    expect(messagesFrom(messages, TOKEN_RULE)).toEqual([]);
  });
});

const LAYER_RULE = "no-restricted-imports";

describe("layer separation", () => {
  it("fails when a screen imports a Layer-A primitive directly", async () => {
    const messages = await lintFixture(
      "components/screens/TicketsScreen.tsx",
      `import { Button } from "@/components/ui/button";\nexport const S = () => <Button>x</Button>;`,
    );

    const errors = messagesFrom(messages, LAYER_RULE);
    expect(errors.length).toBeGreaterThan(0);
    expect(errors[0]!.message).toMatch(/compose domain components/i);
  });

  it("fails on a relative reach into Layer A", async () => {
    const messages = await lintFixture(
      "components/screens/TicketsScreen.tsx",
      `import { Button } from "../ui/button";\nexport const S = () => <Button>x</Button>;`,
    );

    expect(messagesFrom(messages, LAYER_RULE).length).toBeGreaterThan(0);
  });

  it("allows a screen to compose Layer B", async () => {
    const messages = await lintFixture(
      "components/screens/TicketsScreen.tsx",
      `import { DataTable } from "@/components/domain/DataTable/DataTable";\nexport const S = () => <DataTable caption="x" columns={[]} rows={[]} getRowId={() => ""} />;`,
    );

    expect(messagesFrom(messages, LAYER_RULE)).toEqual([]);
  });

  it("still allows Layer B itself to use Layer A", async () => {
    // The restriction is on screens, not on domain components — Layer B is
    // exactly where a primitive is supposed to be wrapped.
    const messages = await lintFixture(
      "components/domain/Thing.tsx",
      `import { Button } from "@/components/ui/button";\nexport const T = () => <Button>x</Button>;`,
    );

    expect(messagesFrom(messages, LAYER_RULE)).toEqual([]);
  });
});

const LITERAL_JSX_RULE = "design-system/no-literal-jsx-strings";

describe("hard-coded JSX text ban", () => {
  it("rejects literal prose in an element", async () => {
    const messages = await lintFixture(
      "components/shell/Fixture.tsx",
      `export const F = () => <span>Ragab CRM</span>;`,
    );

    const errors = messagesFrom(messages, LITERAL_JSX_RULE);
    expect(errors.length).toBeGreaterThan(0);
    expect(errors[0]!.message).toMatch(/externalise into messages/i);
  });

  it("rejects a literal wearing braces", async () => {
    const messages = await lintFixture(
      "components/shell/Fixture.tsx",
      `export const F = () => <span>{"Ragab CRM"}</span>;`,
    );

    expect(messagesFrom(messages, LITERAL_JSX_RULE).length).toBeGreaterThan(0);
  });

  it("accepts a translation lookup", async () => {
    const messages = await lintFixture(
      "components/shell/Fixture.tsx",
      `export const F = ({ t }: { t: (k: string) => string }) => <span>{t("shell.brand")}</span>;`,
    );

    // The rule inspects JSX children, not string arguments to calls.
    expect(messagesFrom(messages, LITERAL_JSX_RULE)).toEqual([]);
  });

  it("accepts an interpolated value", async () => {
    const messages = await lintFixture(
      "components/shell/Fixture.tsx",
      `export const F = ({ count }: { count: number }) => <span>{count}</span>;`,
    );

    expect(messagesFrom(messages, LITERAL_JSX_RULE)).toEqual([]);
  });

  it("accepts numeric and punctuation-only text", async () => {
    // A rendered count is a formatted number, not a translation.
    const messages = await lintFixture(
      "components/shell/Fixture.tsx",
      `export const F = () => <span>— 1,284 · 42%</span>;`,
    );

    expect(messagesFrom(messages, LITERAL_JSX_RULE)).toEqual([]);
  });

  it("accepts Arabic prose no more than English — both must be externalised", async () => {
    const messages = await lintFixture(
      "components/shell/Fixture.tsx",
      `export const F = () => <span>الرئيسية</span>;`,
    );

    expect(messagesFrom(messages, LITERAL_JSX_RULE).length).toBeGreaterThan(0);
  });

  it("honours an explicit data-i18n-ignore opt-out", async () => {
    const messages = await lintFixture(
      "components/shell/Fixture.tsx",
      `export const F = () => <span data-i18n-ignore>SLA</span>;`,
    );

    expect(messagesFrom(messages, LITERAL_JSX_RULE)).toEqual([]);
  });

  it("does not police test files", async () => {
    const messages = await lintFixture(
      "__tests__/components/Fixture.test.tsx",
      `export const F = () => <span>Ragab CRM</span>;`,
    );

    expect(messagesFrom(messages, LITERAL_JSX_RULE)).toEqual([]);
  });
});

const DIRECT_INTL_RULE = "design-system/no-direct-intl-formatting";

describe("one formatting layer", () => {
  it.each([
    `new Intl.DateTimeFormat("en").format(new Date());`,
    `new Intl.NumberFormat("en").format(1);`,
    `new Intl.RelativeTimeFormat("en").format(1, "day");`,
    `new Intl.ListFormat("en").format(["a"]);`,
    `new Intl.PluralRules("en").select(1);`,
  ])("rejects %s outside lib/format", async (source) => {
    const messages = await lintFixture("components/shell/Fixture.tsx", `export const x = ${source}`);

    const errors = messagesFrom(messages, DIRECT_INTL_RULE);
    expect(errors.length).toBeGreaterThan(0);
    expect(errors[0]!.message).toMatch(/lib\/format/);
  });

  it.each(["toLocaleString", "toLocaleDateString", "toLocaleTimeString"])(
    "rejects %s",
    async (method) => {
      const messages = await lintFixture(
        "components/shell/Fixture.tsx",
        `export const x = new Date().${method}();`,
      );

      expect(messagesFrom(messages, DIRECT_INTL_RULE).length).toBeGreaterThan(0);
    },
  );

  it("allows the formatting layer itself to call Intl", async () => {
    // lib/format IS the layer; the ban is on everywhere else reaching past it.
    const messages = await lintFixture(
      "lib/format/index.ts",
      `export const x = new Intl.NumberFormat("en-US").format(1);`,
    );

    expect(messagesFrom(messages, DIRECT_INTL_RULE)).toEqual([]);
  });

  it("accepts going through the formatting layer", async () => {
    const messages = await lintFixture(
      "components/shell/Fixture.tsx",
      `import { formatDate } from "@/lib/format";\nexport const x = formatDate(new Date(), "en");`,
    );

    expect(messagesFrom(messages, DIRECT_INTL_RULE)).toEqual([]);
  });

  it("does not police test files", async () => {
    const messages = await lintFixture(
      "__tests__/lib/Fixture.test.ts",
      `export const x = new Intl.NumberFormat("en").format(1);`,
    );

    expect(messagesFrom(messages, DIRECT_INTL_RULE)).toEqual([]);
  });
});

const BREAKPOINT_RULE = "design-system/no-adhoc-breakpoint";

describe("three responsive bands", () => {
  it.each(["sm", "md", "lg", "xl", "2xl"])("rejects the bare screen %s:", async (screen) => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `export const F = () => <div className="${screen}:flex">{null}</div>;`,
    );

    const errors = messagesFrom(messages, BREAKPOINT_RULE);
    expect(errors.length).toBeGreaterThan(0);
    expect(errors[0]!.message).toMatch(/named band/i);
  });

  it("rejects a bare screen chained after another variant", async () => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `export const F = () => <div className="data-[side=left]:md:hidden">{null}</div>;`,
    );

    expect(messagesFrom(messages, BREAKPOINT_RULE).length).toBeGreaterThan(0);
  });

  it("rejects a bare screen in a hoisted class constant", async () => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `const FIXTURE_CLASSES = "md:grid"; export const F = () => <div className={FIXTURE_CLASSES} />;`,
    );

    expect(messagesFrom(messages, BREAKPOINT_RULE).length).toBeGreaterThan(0);
  });

  it.each(["mobile", "tablet", "desktop"])("accepts the named band %s:", async (band) => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `export const F = () => <div className="${band}:flex">{null}</div>;`,
    );

    expect(messagesFrom(messages, BREAKPOINT_RULE)).toEqual([]);
  });

  it("rejects a hand-rolled media query", async () => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `const CSS = "@media (min-width: 600px) { .x { color: red } }"; export const F = () => CSS;`,
    );

    expect(messagesFrom(messages, BREAKPOINT_RULE).length).toBeGreaterThan(0);
  });

  it("rejects a media query in a template literal", async () => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      "const CSS = `@media (max-width: 480px) { .x { display: none } }`; export const F = () => CSS;",
    );

    expect(messagesFrom(messages, BREAKPOINT_RULE).length).toBeGreaterThan(0);
  });

  it("does not misfire on a variant key that merely reads like a screen", async () => {
    // Button's size variants are named sm/md/lg. They are object keys, not
    // class tokens, and flagging them would make the rule unusable.
    const messages = await lintFixture(
      "components/ui/Fixture.tsx",
      `export const sizes = { sm: "h-7", md: "h-8", lg: "h-9" };`,
    );

    expect(messagesFrom(messages, BREAKPOINT_RULE)).toEqual([]);
  });

  it("does not misfire on prose containing the word medium", async () => {
    const messages = await lintFixture(
      "components/domain/Fixture.tsx",
      `export const F = () => <div className="font-medium text-sm">{null}</div>;`,
    );

    expect(messagesFrom(messages, BREAKPOINT_RULE)).toEqual([]);
  });

  it("does not police test files", async () => {
    const messages = await lintFixture(
      "__tests__/components/Fixture.test.tsx",
      `export const F = () => <div className="md:flex">{null}</div>;`,
    );

    expect(messagesFrom(messages, BREAKPOINT_RULE)).toEqual([]);
  });
});
