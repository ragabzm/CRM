#!/usr/bin/env node
/*
 * Builds the chat widget: the one artifact in this repository that is not the
 * application.
 *
 * It is the exception AD-13 allows, and it is kept honest by being tiny. The
 * widget is embedded on third-party websites, so it must not carry the
 * application's React tree, its router, its i18n runtime or its API client —
 * shipping any of those to somebody else's page would mean a bug in our
 * dashboard could break their homepage.
 *
 * What it DOES inherit is the design tokens, so the widget looks like the
 * product rather than like a third-party bolt-on. That is why this script
 * exists at all: `tokens/tokens.css` is written for Tailwind, whose `@theme`
 * block a browser ignores, so the primitives have to be re-emitted as ordinary
 * custom properties for a page with no build step.
 *
 * The output lands in `public/widget/`, served by the application's own web
 * server. There is no fourth artifact and no second deployment.
 */
import { mkdir, readFile, writeFile, copyFile } from "node:fs/promises";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, "..");
const out = join(root, "public", "widget");

/**
 * Tailwind's `@theme` and `@layer theme` wrappers, removed.
 *
 * A browser ignores an unknown at-rule and everything inside it, so left as
 * they are the primitives would never be declared and every semantic token
 * would resolve to nothing — a widget with no colours at all, and no error
 * anywhere saying why.
 */
function plainCss(source) {
  return (
    source
      .replace(/@theme\s*\{/g, ":root {")
      .replace(/@layer\s+theme\s*\{/g, "")
      /*
       * The stray brace left by unwrapping `@layer theme`. It is the LAST one in
       * the file, so removing the final closing brace restores balance. Asserted
       * below rather than assumed.
       */
      .replace(/\}\s*$/, "")
  );
}

const source = await readFile(join(root, "tokens", "tokens.css"), "utf8");
const css = plainCss(source);

const opens = (css.match(/\{/g) ?? []).length;
const closes = (css.match(/\}/g) ?? []).length;

if (opens !== closes) {
  /*
   * Fail loudly. An unbalanced stylesheet does not throw in a browser — it
   * silently drops everything after the mistake, which would show up as a
   * widget that is slightly the wrong colour and nothing else.
   */
  throw new Error(
    `The generated token stylesheet is unbalanced (${opens} open, ${closes} close). ` +
      "tokens.css has changed shape and scripts/build-widget.mjs needs to change with it.",
  );
}

await mkdir(out, { recursive: true });
await writeFile(join(out, "tokens.css"), css, "utf8");
await copyFile(join(root, "widget", "loader.js"), join(out, "loader.js"));
await copyFile(join(root, "widget", "frame.html"), join(out, "frame.html"));

console.log(`Chat widget written to public/widget (${css.length} bytes of tokens).`);
