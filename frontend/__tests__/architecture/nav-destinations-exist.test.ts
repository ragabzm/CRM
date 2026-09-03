import { existsSync } from "node:fs";
import { readFileSync } from "node:fs";

import { describe, expect, it } from "vitest";

/**
 * Every sidebar destination is a route that exists.
 *
 * The Tickets link pointed at `/tickets` for weeks while `app/tickets/` did
 * not exist, so the primary navigation had a dead entry that answered 404 —
 * and nothing failed, because no test connected the two.
 */

const APP = "app/(app)";

/** `/customers/[id]` is a route; `/customers` is the segment a link names. */
function routeExists(href: string): boolean {
  if (href === "/") return existsSync(`${APP}/page.tsx`);

  const segment = href.replace(/^\//, "");

  return existsSync(`${APP}/${segment}/page.tsx`);
}

describe("the sidebar", () => {
  const source = readFileSync("components/shell/Sidebar.tsx", "utf8");
  const hrefs = [...source.matchAll(/href:\s*"([^"]+)"/g)].map((match) => match[1]!);

  it("names at least the destinations the product has", () => {
    // A broken regex would find none and pass every assertion below.
    expect(hrefs.length).toBeGreaterThanOrEqual(3);
  });

  it("points every entry at a route that exists", () => {
    const dead = hrefs.filter((href) => !routeExists(href));

    expect(dead).toEqual([]);
  });
});
