import { render, screen, waitFor, withIntl } from "@/__tests__/helpers/intl";
import { fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/admin/knowledge",
}));

import { ArticleLifecycleControls } from "@/components/domain/ArticleLifecycleControls/ArticleLifecycleControls";
import { KnowledgeSection } from "@/components/screens/admin/KnowledgeSection";
import en from "@/messages/en.json";

/**
 * The two things this screen must get right.
 *
 * An article's state and its audience are separate facts, shown separately —
 * "internal and published" and "public and draft" are both ordinary, and a
 * single status chip would make them indistinguishable.
 *
 * And Delete is offered or explained, never silently absent. A control that
 * vanishes teaches an author that the feature is missing; one that is disabled
 * with a reason teaches them the rule.
 */

const DRAFT = {
  id: "01ARTICLEDRAFT00000000000A",
  type: "faq" as const,
  category_id: 1,
  internal_only: true,
  status: "draft" as const,
  default_locale: "en" as const,
  available_locales: ["en" as const],
  title: "How refunds work" as string | null,
  is_customer_visible: false,
  can_delete: true,
  has_been_published: false,
  published_at: null,
  published_by: null,
  archived_at: null,
  archived_by: null,
  created_at: null,
  updated_at: null,
};

const PUBLISHED = {
  ...DRAFT,
  id: "01ARTICLEPUBLISHED0000000B",
  internal_only: false,
  status: "published" as const,
  available_locales: ["en" as const, "ar" as const],
  title: "Cancelling a subscription",
  is_customer_visible: true,
  // The whole point: published once, never deletable again.
  can_delete: false,
  has_been_published: true,
};

let articles = [DRAFT, PUBLISHED];

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  articles = [DRAFT, PUBLISHED];

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);

      if (url.includes("/knowledge/categories")) {
        return json({
          data: [
            { id: 1, name: { en: "Billing", ar: "الفوترة" }, sort_order: 1, article_count: 2 },
          ],
        });
      }

      if (url.includes("/knowledge/articles/")) return json(articles[1]);

      return json({ data: articles, meta: { total: articles.length } });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

describe("the knowledge base list", () => {
  it("shows state and audience as two separate facts", async () => {
    const { container } = render(<KnowledgeSection />);

    await waitFor(() =>
      expect(container.querySelectorAll("[data-slot='status-chip']")).toHaveLength(2),
    );

    const states = [...container.querySelectorAll("[data-slot='status-chip']")].map((n) =>
      n.getAttribute("data-status"),
    );
    const audiences = [...container.querySelectorAll("[data-slot='audience-chip']")].map((n) =>
      n.getAttribute("data-audience"),
    );

    /*
     * An internal DRAFT and a public PUBLISHED one. Collapsing the two axes
     * into a single status would make "internal and published" — the agent
     * handbook — impossible to express.
     */
    expect(states).toEqual(["draft", "published"]);
    expect(audiences).toEqual(["internal", "public"]);
  });

  it("shows a title a person can read, not an identifier", async () => {
    render(<KnowledgeSection />);

    // The identifier is the one thing about an article that means nothing to
    // the person reading the list.
    await waitFor(() => expect(screen.getByText("How refunds work")).toBeInTheDocument());
    expect(screen.getByText("Cancelling a subscription")).toBeInTheDocument();
    expect(screen.queryByText(DRAFT.id)).toBeNull();
  });

  it("marks an article with no words yet rather than showing nothing", async () => {
    articles = [{ ...DRAFT, title: null, available_locales: [] }];

    render(<KnowledgeSection />);

    await waitFor(() => expect(screen.getByText(en.admin.knowledge.untitled)).toBeInTheDocument());
  });

  it("says which languages an article exists in", async () => {
    const { container } = render(<KnowledgeSection />);

    await waitFor(() =>
      expect(container.querySelectorAll("[data-slot='languages']")).toHaveLength(2),
    );

    const languages = [...container.querySelectorAll("[data-slot='languages']")].map(
      (n) => n.textContent,
    );

    // An article in one language is complete, and the chip says so plainly
    // rather than leaving it looking half-finished.
    expect(languages[0]).toBe(en.admin.knowledge.locale.en);
    expect(languages[1]).toContain(en.admin.knowledge.locale.ar);
  });

  it("names the category in the reader's own language", async () => {
    const { unmount } = render(<KnowledgeSection />);

    await waitFor(() => expect(screen.getAllByText("Billing").length).toBeGreaterThan(0));
    unmount();

    render(<KnowledgeSection />, { locale: "ar" });

    /*
     * A category list rendered in English inside an Arabic page is the small
     * inconsistency that makes a whole screen feel half-translated. It was
     * hard-coded to English once already, which is how it survives review.
     */
    await waitFor(() => expect(screen.getAllByText("الفوترة").length).toBeGreaterThan(0));
    expect(screen.queryByText("Billing")).toBeNull();
  });

  it("refuses to let an author start writing with no category to file it in", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async (input: RequestInfo | URL) => {
        const url = String(input);
        if (url.includes("/knowledge/categories")) return json({ data: [] });

        return json({ data: [], meta: { total: 0 } });
      }),
    );

    render(<KnowledgeSection />);

    // Said plainly, rather than leaving a disabled button nobody can explain.
    await waitFor(() =>
      expect(screen.getByText(en.admin.knowledge.needsACategory)).toBeInTheDocument(),
    );
  });
});

describe("the lifecycle controls", () => {
  it("offers Publish on a draft and Archive on a published article", () => {
    const { rerender } = render(
      <ArticleLifecycleControls
        article={DRAFT}
        onPublish={vi.fn()}
        onArchive={vi.fn()}
        onDelete={vi.fn()}
      />,
    );

    expect(screen.getByRole("button", { name: en.admin.knowledge.publish })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: en.admin.knowledge.archive })).toBeNull();

    // Through `withIntl`: the rerender from a wrapped render replaces the
    // whole tree, provider included.
    rerender(
      withIntl(
        <ArticleLifecycleControls
          article={PUBLISHED}
          onPublish={vi.fn()}
          onArchive={vi.fn()}
          onDelete={vi.fn()}
        />,
      ),
    );

    // One transition at a time. Offering the one it is not in is how somebody
    // archives a draft.
    expect(screen.getByRole("button", { name: en.admin.knowledge.archive })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: en.admin.knowledge.publish })).toBeNull();
  });

  it("disables Delete on a published article and says why, in text", () => {
    render(
      <ArticleLifecycleControls
        article={PUBLISHED}
        onPublish={vi.fn()}
        onArchive={vi.fn()}
        onDelete={vi.fn()}
      />,
    );

    expect(screen.getByRole("button", { name: en.admin.knowledge.delete })).toBeDisabled();

    /*
     * In text, not only in a tooltip. A `title` is invisible to touch and is
     * not reliably announced — which would leave exactly the readers who most
     * need the explanation without one.
     */
    expect(screen.getByText(en.admin.knowledge.deleteBlocked)).toBeInTheDocument();
  });

  it("offers Delete on an article nobody ever saw, and asks first", async () => {
    const onDelete = vi.fn();

    render(
      <ArticleLifecycleControls
        article={DRAFT}
        onPublish={vi.fn()}
        onArchive={vi.fn()}
        onDelete={onDelete}
      />,
    );

    const deleteButton = screen.getByRole("button", { name: en.admin.knowledge.delete });
    expect(deleteButton).toBeEnabled();
    expect(screen.queryByText(en.admin.knowledge.deleteBlocked)).toBeNull();

    await userEvent.click(deleteButton);

    // The dialog names the consequence rather than asking "are you sure?".
    expect(screen.getByText(en.admin.knowledge.deleteConsequence)).toBeInTheDocument();
    expect(onDelete).not.toHaveBeenCalled();
  });
});
