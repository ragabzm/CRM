import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import { fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), refresh: vi.fn() }),
  usePathname: () => "/portal/help",
}));

import { HelpArticleScreen } from "@/components/screens/portal/HelpArticleScreen";
import { HelpCentreScreen } from "@/components/screens/portal/HelpCentreScreen";
import en from "@/messages/en.json";

/**
 * The help centre a customer reads.
 *
 * Search first, browse second — somebody arrives with a question in words, not
 * with a taxonomy in mind. And an empty result has to say so and offer the way
 * on, because a blank panel is indistinguishable from a broken page and
 * somebody who cannot tell which will simply leave.
 */

let articles = [
  {
    id: "01A",
    title: "Refund policy",
    category_id: 1,
    served_locale: "en" as const,
    available_locales: ["en" as const],
  },
];
let categories = [{ id: 1, name: "Billing" }];
let articleStatus = 200;

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  articles = [
    {
      id: "01A",
      title: "Refund policy",
      category_id: 1,
      served_locale: "en",
      available_locales: ["en"],
    },
  ];
  categories = [{ id: 1, name: "Billing" }];
  articleStatus = 200;

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);

      if (/\/help\/articles\/[^?]/.test(url)) {
        if (articleStatus !== 200) {
          return json(
            {
              status: articleStatus,
              code: "knowledge.article_unavailable",
              detail: "It may have been withdrawn.",
            },
            articleStatus,
          );
        }

        return json({
          id: "01A",
          title: "Refund policy",
          body: "<p>Five working days.</p>",
          category_id: 1,
          served_locale: "en",
          available_locales: ["en"],
          updated_at: null,
        });
      }

      return json({ data: articles, categories });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

describe("the help centre", () => {
  it("opens on search, with browsing beside it rather than after it", async () => {
    render(<HelpCentreScreen />);

    // The search box is there from the first paint.
    expect(screen.getByLabelText(en.portal.help.search)).toBeInTheDocument();

    /*
     * And so are the categories — not revealed after a failed search. Building
     * the dead end first and the way out second is how a help centre teaches
     * people to give up and write in.
     */
    await waitFor(() => expect(screen.getByText("Billing")).toBeInTheDocument());
  });

  it("needs no sign-in and asks for none", async () => {
    render(<HelpCentreScreen />);

    await waitFor(() => expect(screen.getByText("Refund policy")).toBeInTheDocument());

    expect(screen.queryByLabelText(/password/i)).toBeNull();
    expect(screen.queryByRole("button", { name: /sign in/i })).toBeNull();
  });

  it("says an empty result is empty and offers the way on", async () => {
    articles = [];

    render(<HelpCentreScreen />);

    fireEvent.change(screen.getByLabelText(en.portal.help.search), {
      target: { value: "somethingnobodywroteabout" },
    });

    // UX-06, and a link to ask instead rather than a dead end.
    await waitFor(() => expect(screen.getByText(en.portal.help.noResultsHint)).toBeInTheDocument());

    expect(screen.getByRole("link", { name: en.portal.help.askInstead })).toHaveAttribute(
      "href",
      "/portal/submit",
    );
  });

  it("renders an article's formatting rather than its markup", async () => {
    const { container } = render(<HelpArticleScreen id="01A" />);

    await waitFor(() => expect(screen.getByText("Refund policy")).toBeInTheDocument());

    /*
     * The body was sanitised on WRITE to an explicit allow-list, and rendering
     * is what that sanitising was for. Escaping here would show an article's
     * markup to the reader instead of its formatting.
     */
    // The BODY, not the first paragraph on screen — the language notice above
    // it is also a <p>.
    const paragraph = container.querySelector("[data-slot='article-body'] p");

    expect(paragraph).not.toBeNull();
    expect(paragraph!.textContent).toBe("Five working days.");
    expect(container.textContent).not.toContain("<p>");
  });

  it("tells somebody following a dead link what happened, not that the site is broken", async () => {
    articleStatus = 404;

    render(<HelpArticleScreen id="01GONE" />);

    /*
     * The server's own words. A link to an article since archived should say
     * the answer is gone and offer a way on — never show a body that was
     * withdrawn on purpose.
     */
    await waitFor(() =>
      expect(screen.getByText("It may have been withdrawn.")).toBeInTheDocument(),
    );

    expect(screen.getByRole("link", { name: en.portal.help.backToHelp })).toBeInTheDocument();
    expect(screen.queryByText("Five working days.")).toBeNull();
  });

  it("says which language a single-language article is in", async () => {
    const { container } = render(<HelpArticleScreen id="01A" />);

    await waitFor(() => expect(screen.getByText("Refund policy")).toBeInTheDocument());

    // Said once, plainly. The alternative is a reader who assumes the English
    // page IS the Arabic version.
    expect(container.querySelector("[data-slot='served-locale']")).not.toBeNull();
  });
});
