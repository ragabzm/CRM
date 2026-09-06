import { readFile } from "node:fs/promises";
import { join } from "node:path";

/**
 * Serves the widget's iframe document with the one header that cannot be a
 * static file: `frame-ancestors`.
 *
 * THIS is the enforced half of "embedding is constrained by an origin
 * allow-list". The list checked when a conversation opens is advisory and
 * cannot be otherwise — the request arrives from the iframe, whose origin is
 * ours, so the embedding site is something the page CLAIMS rather than
 * something the server observes. Anybody who owns the host page can claim
 * whatever they like.
 *
 * `frame-ancestors` is different: the browser refuses to render the frame at
 * all on a site that is not listed, and no script on that page can talk it out
 * of the decision. So the allow-list stops a casual copy-paste, and this stops
 * the rest.
 *
 * The DOCUMENT is still the separate artifact: this handler reads the built
 * file and returns it unchanged. Only its headers come from the application.
 */
export const dynamic = "force-dynamic";

async function allowedOrigins(): Promise<string[]> {
  const base = process.env.API_INTERNAL_BASE_URL ?? "http://localhost:8000/api/v1";

  try {
    const response = await fetch(`${base}/chat/embed-origins`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });

    if (!response.ok) {
      return [];
    }

    const body = (await response.json()) as { data?: { origins?: unknown } };
    const origins = body.data?.origins;

    return Array.isArray(origins) ? origins.filter((o): o is string => typeof o === "string") : [];
  } catch {
    /*
     * The API is unreachable. Return NOTHING, which makes the policy
     * `frame-ancestors 'self'` — the widget still works on our own portal and
     * refuses every third-party site.
     *
     * Failing closed matters here: the alternative is a blip in one service
     * quietly turning the embedding restriction off across every site that has
     * ever pasted the loader in.
     */
    return [];
  }
}

export async function GET(): Promise<Response> {
  const [html, origins] = await Promise.all([
    readFile(join(process.cwd(), "public", "widget", "frame.html"), "utf8"),
    allowedOrigins(),
  ]);

  /*
   * The API base, injected rather than derived.
   *
   * The frame is served by the frontend and the API is a different origin —
   * `NEXT_PUBLIC_API_BASE_URL` is the one the BROWSER can reach, not the
   * internal one this process uses. Getting these two the wrong way round
   * sends every call from a visitor's browser to a hostname that only exists
   * inside the container network.
   */
  const publicApi = process.env.NEXT_PUBLIC_API_BASE_URL ?? "/api/v1";

  const withApi = html.replace(
    "<script>",
    `<script>window.__CHAT_API__=${JSON.stringify(publicApi)};</script>\n    <script>`,
  );

  return new Response(withApi, {
    headers: {
      "Content-Type": "text/html; charset=utf-8",
      /*
       * `'self'` always, so the widget works on the customer portal without an
       * administrator having to add their own site to a list before chat works
       * on it — a first-run failure that reads as a bug.
       */
      "Content-Security-Policy": `frame-ancestors 'self' ${origins.join(" ")}`.trim(),
      // The document is small and the policy changes with a setting.
      "Cache-Control": "no-store",
    },
  });
}
