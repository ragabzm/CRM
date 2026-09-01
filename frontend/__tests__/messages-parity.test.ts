import { describe, expect, it } from "vitest";

import ar from "@/messages/ar.json";
import en from "@/messages/en.json";

type Tree = { [key: string]: string | Tree };

/** Dotted paths to every leaf. */
function leafKeys(tree: Tree, prefix = ""): string[] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key;
    return typeof value === "string" ? [path] : leafKeys(value, path);
  });
}

/** ICU placeholders and rich-text tags a message declares. */
function placeholders(message: string): string[] {
  return [...message.matchAll(/\{(\w+)[^}]*\}|<(\w+)>/g)]
    .map((match) => match[1] ?? match[2]!)
    .sort();
}

const enKeys = leafKeys(en as Tree);
const arKeys = leafKeys(ar as Tree);

describe("message catalogues stay in step", () => {
  it("has no key present in English but missing from Arabic", () => {
    // A key missing here is a string an Arabic reader sees in English. It still
    // renders, so nothing looks broken — which is why it needs a test.
    expect(enKeys.filter((key) => !arKeys.includes(key))).toEqual([]);
  });

  it("has no key present in Arabic but missing from English", () => {
    // The other direction matters too: English is the fallback, so an
    // Arabic-only key can never be reached.
    expect(arKeys.filter((key) => !enKeys.includes(key))).toEqual([]);
  });

  it("declares the same placeholders in both languages", () => {
    const mismatches: string[] = [];

    for (const key of enKeys) {
      const read = (tree: Tree) =>
        key.split(".").reduce<string | Tree>((node, part) => (node as Tree)[part]!, tree) as string;

      const enPlaceholders = placeholders(read(en as Tree));
      const arPlaceholders = placeholders(read(ar as Tree));

      if (enPlaceholders.join(",") !== arPlaceholders.join(",")) {
        mismatches.push(`${key}: en[${enPlaceholders}] vs ar[${arPlaceholders}]`);
      }
    }

    // A translation that drops {page} renders a sentence with a hole in it.
    expect(mismatches).toEqual([]);
  });

  it("has no empty string values", () => {
    const empties = enKeys.filter((key) => {
      const read = (tree: Tree) =>
        key.split(".").reduce<string | Tree>((node, part) => (node as Tree)[part]!, tree) as string;
      return read(en as Tree).trim() === "" || read(ar as Tree).trim() === "";
    });

    expect(empties).toEqual([]);
  });

  it("offers the other language's endonym on the toggle", () => {
    // Each locale's toggle names the language you would switch TO; a control
    // named after the current state reads as a status, not an action.
    expect(en.shell.actions.toggleLanguage).toBe("العربية");
    expect(ar.shell.actions.toggleLanguage).toBe("English");
  });

  it("actually translates the Arabic catalogue", () => {
    /*
     * Endonyms are deliberately identical in both catalogues: a language picker
     * names each language in its OWN language, so "English" stays "English" for
     * an Arabic reader and "العربية" stays "العربية" for an English one. That is
     * the whole point of an endonym — translating them makes the control harder
     * to use, not easier.
     *
     * Listed explicitly so a genuinely untranslated string cannot hide here.
     */
    const ENDONYMS = [
      "shell.actions.toggleLanguage",
      "profile.languageEnglish",
      "profile.languageArabic",
    ];

    const untranslated = enKeys.filter((key) => {
      if (ENDONYMS.includes(key)) return false;

      const read = (tree: Tree) =>
        key.split(".").reduce<string | Tree>((node, part) => (node as Tree)[part]!, tree) as string;
      return read(en as Tree) === read(ar as Tree);
    });

    expect(untranslated).toEqual([]);
  });

  it("names each language in its own language", () => {
    expect(en.profile.languageEnglish).toBe(ar.profile.languageEnglish);
    expect(en.profile.languageArabic).toBe(ar.profile.languageArabic);
  });
});
