import { render, screen } from "@/__tests__/helpers/intl";
import { describe, expect, it } from "vitest";

import { RuleBlockedRefusal } from "@/components/domain/RuleBlockedRefusal/RuleBlockedRefusal";
import type { Problem } from "@/lib/api/client";

function problem(extras: Record<string, unknown> = {}): Problem {
  return {
    type: "https://ragab.example/problems/tickets/category-in-use",
    title: "Category is still in use",
    status: 409,
    detail: "Cannot delete: 7 tickets still use this category.",
    code: "tickets.category_in_use",
    ...extras,
  } as Problem;
}

describe("RuleBlockedRefusal answers the reader's next question", () => {
  it("prints the count and links to the blocked records", () => {
    render(<RuleBlockedRefusal problem={problem({ count: 7, path: "/tickets?category=3" })} />);

    // The count says how big the problem is...
    expect(screen.getByRole("alert")).toHaveTextContent("7 tickets still use it");
    // ...and the link is the route to fixing it.
    expect(screen.getByRole("link", { name: "View them" })).toHaveAttribute(
      "href",
      "/tickets?category=3",
    );
  });

  it("falls back to the server's own detail when the extensions are absent", () => {
    // A refusal with less information still has to render something true.
    render(<RuleBlockedRefusal problem={problem()} />);

    expect(screen.getByRole("alert")).toHaveTextContent(
      "Cannot delete: 7 tickets still use this category.",
    );
    expect(screen.queryByRole("link")).not.toBeInTheDocument();
  });

  it("ignores a count that is not a number", () => {
    render(<RuleBlockedRefusal problem={problem({ count: "seven", path: "/tickets" })} />);

    expect(screen.queryByRole("link")).not.toBeInTheDocument();
  });

  it("ignores an empty path rather than rendering a dead link", () => {
    render(<RuleBlockedRefusal problem={problem({ count: 7, path: "" })} />);

    expect(screen.queryByRole("link")).not.toBeInTheDocument();
  });

  it("renders nothing when there is no problem", () => {
    const { container } = render(<RuleBlockedRefusal problem={null} />);

    expect(container).toBeEmptyDOMElement();
  });
});
