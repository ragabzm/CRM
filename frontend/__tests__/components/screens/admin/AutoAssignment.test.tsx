import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/admin/auto-assignment",
}));

import { AutoAssignmentSection } from "@/components/screens/admin/AutoAssignmentSection";
import en from "@/messages/en.json";

/**
 * A lookup table an administrator can read in one line per row.
 *
 * Two of these tests are about what the screen SAYS rather than what it does.
 * The precedence has to be stated, because an administrator who discovers it
 * from a ticket that went somewhere unexpected has already been surprised. And
 * a row whose target has been deactivated has to say so, because a rule that
 * quietly never fires looks exactly like one that works.
 */

let mappings = [
  {
    id: 1,
    source_type: "category" as const,
    source_id: 10,
    target_type: "agent" as const,
    target_id: 7,
    active: true,
    inactive_reason: null as string | null,
  },
];

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  mappings = [
    {
      id: 1,
      source_type: "category",
      source_id: 10,
      target_type: "agent",
      target_id: 7,
      active: true,
      inactive_reason: null,
    },
  ];

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);

      if (url.includes("/assignment-mappings")) {
        return json({ data: mappings, precedence: ["category", "department"] });
      }
      if (url.includes("/categories")) {
        return json({ data: [{ id: 10, name: { en: "Billing", ar: "الفوترة" }, sort_order: 1 }] });
      }
      if (url.includes("/departments")) return json({ data: [{ id: 2, name: "Support" }] });

      return json({ data: [{ id: 7, name: "Dana Faris" }] });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

describe("the auto-assignment rules", () => {
  it("reads one line per rule: a source and a target", async () => {
    const { container } = render(<AutoAssignmentSection />);

    /*
     * Scoped to the ROW, not the whole screen: the same names appear again in
     * the "add a rule" pickers below, and `getByText` would find two.
     */
    await waitFor(() => expect(container.querySelectorAll("tbody tr")).toHaveLength(1));

    const cells = [...container.querySelectorAll("tbody tr td")].map((c) => c.textContent ?? "");

    expect(cells[0]).toContain("Billing");
    expect(cells[1]).toContain("Dana Faris");
  });

  it("states the precedence rather than leaving it to be discovered", async () => {
    const { container } = render(<AutoAssignmentSection />);

    await waitFor(() => expect(container.querySelector("[data-slot='precedence']")).not.toBeNull());

    /*
     * Category before department, and taken from the SERVER so this screen
     * cannot drift from the rule the code applies.
     */
    const said = container.querySelector("[data-slot='precedence']")!.textContent ?? "";

    expect(said).toContain(en.admin.autoAssignment.source.category);
    expect(said).toContain(en.admin.autoAssignment.source.department);
  });

  it("says which rows cannot fire, and why", async () => {
    mappings = [
      {
        id: 1,
        source_type: "category",
        source_id: 10,
        target_type: "agent",
        target_id: 7,
        active: false,
        inactive_reason: "That account has been deactivated.",
      },
    ];

    const { container } = render(<AutoAssignmentSection />);

    /*
     * A mapping pointing at somebody who left is worse than no mapping: the
     * queue looks like it is being sorted while every matching ticket quietly
     * stays unassigned.
     */
    await waitFor(() =>
      expect(screen.getByText("That account has been deactivated.")).toBeInTheDocument(),
    );

    expect(
      container.querySelector("[data-slot='mapping-state']")?.getAttribute("data-active"),
    ).toBe("false");
  });

  it("offers no ordering, no conditions and no enable switch", async () => {
    const { container } = render(<AutoAssignmentSection />);

    await waitFor(() => expect(container.querySelectorAll("tbody tr")).toHaveLength(1));

    /*
     * The scope reduction, asserted. Each of these is something a support team
     * asks for on day two, and each needs an availability model that was
     * removed with chat presence.
     */
    const text = container.textContent ?? "";

    for (const absent of ["Move up", "Move down", "Priority", "Condition", "Enabled", "Strategy"]) {
      expect(text).not.toContain(absent);
    }

    expect(container.querySelectorAll("input[type='checkbox']")).toHaveLength(0);
  });

  it("says plainly what happens while nothing is mapped", async () => {
    mappings = [];

    render(<AutoAssignmentSection />);

    // Unassigned is a valid, visible place for a ticket to be — not a failure
    // state the screen should apologise for.
    await waitFor(() =>
      expect(screen.getByText(en.admin.autoAssignment.empty)).toBeInTheDocument(),
    );
    expect(screen.getByText(en.admin.autoAssignment.emptyHint)).toBeInTheDocument();
  });
});
