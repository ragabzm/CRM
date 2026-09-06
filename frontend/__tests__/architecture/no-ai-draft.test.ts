import { readFileSync, readdirSync, statSync } from "node:fs";
import { join } from "node:path";

import { describe, expect, it } from "vitest";

/**
 * There is no AI-draft TREATMENT, and there is not going to be one by accident.
 *
 * The distinction this file now holds is narrower than the one it started
 * with, and the narrowing was a decision rather than a concession.
 *
 * Story 4.4 built the conversation and asserted that no AI panel existed at
 * all — true then, and the right guard while it was. Story 9.2 commissions the
 * panel in as many words. So the panel is allowed; what is still refused is
 * the panel's output appearing IN THE THREAD.
 *
 * The conversation has three semantic treatments — customer message, agent
 * message, internal note — and a machine-written suggestion styled like an
 * agent's own reply would be a fourth thing the reader has to learn to
 * distinguish from three they already know. The failure mode is an agent
 * sending a draft they only skimmed, in their own name.
 *
 * So a draft lives in the panel and in the composer, and nowhere else.
 */

const ROOTS = ["app", "components", "lib"];

function sourceFiles(dir: string): string[] {
  const found: string[] = [];

  for (const name of readdirSync(dir)) {
    if (name === "node_modules" || name.startsWith(".")) continue;

    const path = join(dir, name);

    if (statSync(path).isDirectory()) {
      found.push(...sourceFiles(path));
    } else if (/\.tsx?$/.test(name)) {
      found.push(path);
    }
  }

  return found;
}

/** Comments explain the rule; only real code counts as an implementation. */
function withoutComments(source: string): string {
  return source.replace(/\/\*[\s\S]*?\*\//g, "").replace(/\/\/.*$/gm, "");
}

describe("the conversation", () => {
  const files = ROOTS.flatMap(sourceFiles);

  it("keeps the assistant out of the thread", () => {
    /*
     * The panel exists (Story 9.2). What must not happen is the conversation
     * component learning about it — a draft rendered beside real messages is
     * the fourth treatment this file refuses.
     */
    const conversation = withoutComments(
      readFileSync("components/domain/TicketConversation/ConversationPanel.tsx", "utf8"),
    );

    expect(conversation).not.toMatch(/AiSuggestion|AiLabel|assist/i);
  });

  it("still has the panel it was told to have", () => {
    // The other half. A test that only checked the absence would pass on a
    // product where the assists were never built.
    const panels = files.filter((path) => /AiSuggestionPanel/.test(path));

    expect(panels.length).toBeGreaterThan(0);
  });

  it("has no ai-draft treatment in any component", () => {
    const offenders = files.filter((path) =>
      /["']ai[_-]?draft["']|data-direction=["']ai/i.test(
        withoutComments(readFileSync(path, "utf8")),
      ),
    );

    expect(offenders).toEqual([]);
  });

  it("keeps exactly three message directions", () => {
    const client = readFileSync("lib/api/tickets.ts", "utf8");

    // A fourth would have to be added here first, which makes adding one a
    // visible decision rather than a styling accident.
    expect(client).toContain('export type MessageDirection = "inbound" | "outbound" | "internal";');
  });

  it("scanned a believable number of files", () => {
    // A broken walk would find no offenders and pass every test above.
    expect(files.length).toBeGreaterThan(50);
  });
});
