import { readFileSync } from "node:fs";
import { join } from "node:path";

import { describe, expect, it } from "vitest";

const root = join(__dirname, "..", "..");

/**
 * The source with its prose removed.
 *
 * Matched against CODE only, exactly as the backend's architecture guards do.
 * Without this, a comment explaining why there is no WebSocket fails the test
 * that there is no WebSocket — which teaches whoever hits it to delete the
 * explanation rather than keep the rule.
 */
function codeOnly(source: string): string {
  return source
    .replace(/<!--[\s\S]*?-->/g, "")
    .replace(/\/\*[\s\S]*?\*\//g, "")
    .replace(/^\s*\/\/.*$/gm, "");
}

const loader = codeOnly(readFileSync(join(root, "widget", "loader.js"), "utf8"));
const frame = codeOnly(readFileSync(join(root, "widget", "frame.html"), "utf8"));

/** The frame WITH its prose, for the assertions that are about the prose. */
const frameProse = readFileSync(join(root, "widget", "frame.html"), "utf8");

/**
 * The chat widget stays the one thing here that is not the application.
 *
 * It is AD-13's single exception, and the exception only stays safe while the
 * widget stays tiny. It runs inside an iframe on OTHER PEOPLE'S WEBSITES, so
 * everything of ours that ships in it is something of ours that can break
 * their page — and every capability it holds is a capability an attacker who
 * owns that page is one step closer to.
 *
 * What it inherits is design tokens, so it looks like the product. Nothing
 * else. If somebody ever genuinely needs React in there, deleting this file is
 * the deliberate act that says so.
 */
describe("the chat widget", () => {
  it("imports nothing from the application", () => {
    for (const source of [loader, frame]) {
      /*
       * No bundler, no module graph, no path alias. The moment one of these
       * appears, the widget is being built from the application's tree and the
       * separation is over — quietly, and in a commit that looks like tidying.
       */
      expect(source).not.toMatch(/from\s+["']@\//);
      expect(source).not.toMatch(/require\(/);
      expect(source).not.toMatch(/\breact\b/i);
      expect(source).not.toMatch(/\bnext\/\w/);
      expect(source).not.toMatch(/next-intl/);
    }
  });

  it("takes design tokens and nothing else from the application", () => {
    const stylesheets = [...frame.matchAll(/<link[^>]+href="([^"]+)"/g)].map((m) => m[1]);

    // Exactly one, and it is the generated token sheet beside it.
    expect(stylesheets).toEqual(["./tokens.css"]);
  });

  it("uses only semantic tokens, never the primitives", () => {
    const primitives = [...frame.matchAll(/var\(--color-[a-z]-\d+\)/g)];

    /*
     * The same rule the application follows and for the same reason: a widget
     * that hard-codes `--color-n-800` has taken a decision it does not own,
     * and stops matching the product the first time it is rethemed.
     */
    expect(primitives).toEqual([]);
  });

  it("sends credentials on every call it makes", () => {
    const fetches = [...frame.matchAll(/fetch\(/g)];
    const credentialed = [...frame.matchAll(/credentials:\s*"include"/g)];

    expect(fetches.length).toBeGreaterThan(0);

    /*
     * The token is an http-only cookie, and this is always a cross-origin
     * context — a `fetch` without `credentials: "include"` is an anonymous
     * request that looks exactly like a working one until it 404s.
     *
     * Counted rather than pinned to one. The first version of this assertion
     * required exactly one `fetch`, which held only while there was a single
     * `call()` helper — so adding the branding request broke a test that was
     * never about how many calls there are. What matters is that every one of
     * them is credentialed.
     */
    expect(credentialed.length).toBe(fetches.length);
  });

  it("never writes the token to web storage", () => {
    for (const source of [loader, frame]) {
      expect(source).not.toMatch(/localStorage/);
      expect(source).not.toMatch(/sessionStorage/);
      expect(source).not.toMatch(/indexedDB/i);
    }
  });

  it("opens no persistent connection", () => {
    for (const source of [loader, frame]) {
      expect(source).not.toMatch(/WebSocket/i);
      expect(source).not.toMatch(/EventSource/);
    }
  });

  it("renders text as text, never as markup", () => {
    /*
     * Messages come from the server, and one day some of them will have been
     * typed by a stranger on somebody else's website. `innerHTML` anywhere in
     * this file is a cross-site scripting hole in an iframe embedded on a
     * customer's homepage.
     */
    expect(frame).not.toMatch(/innerHTML/);
    expect(frame).toMatch(/textContent/);
  });

  it("checks the origin of anything the frame says to the host page", () => {
    // `message` events arrive from every frame on the host's page, including
    // ones they embedded from somebody else.
    expect(loader).toMatch(/event\.origin/);
  });

  it("lays itself out for a phone and for Arabic without a second rule", () => {
    // Logical properties, so RTL is a document attribute rather than a
    // stylesheet fork that somebody has to remember to update twice.
    expect(loader).toMatch(/inset-inline-end/);
    expect(frame).toMatch(/margin-inline-start/);

    // Never wider than the viewport on a 390px phone.
    expect(loader).toMatch(/min\(380px, calc\(100vw - 32px\)\)/);
  });

  it("tells a customer when they are talking to the machine", () => {
    /*
     * The chatbot is the one thing in this product that reaches a customer
     * without a colleague having read it, so the one thing they must be able
     * to tell is whether a person wrote this.
     *
     * A WORD, in both languages, and a rule down the edge — never a colour.
     * A tint says nothing in greyscale, nothing to a screen reader and nothing
     * on a printed transcript.
     */
    for (const lang of ["Assistant — not a person", "المساعد — ليس شخصًا"]) {
      expect(frameProse).toContain(lang);
    }

    expect(frame).toMatch(/\.msg\[data-from="assistant"\]/);
    expect(frame).toMatch(/border-inline-start/);
  });

  it("says why there is no typing indicator rather than leaving a gap", () => {
    for (const lang of ["typing indicator", "مؤشر كتابة"]) {
      // The visitor-facing strings, so this reads the file as written.
      expect(frameProse).toContain(lang);
    }
  });
});
