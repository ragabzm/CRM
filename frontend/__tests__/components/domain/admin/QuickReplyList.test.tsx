import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { QuickReplyList } from "@/components/domain/QuickReplyList/QuickReplyList";
import type { QuickReply } from "@/lib/api/admin";

const REPLIES: QuickReply[] = [
  {
    id: "01AAA",
    label: { en: "Greeting", ar: "تحية" },
    body: { en: "Hello there", ar: "مرحبا" },
  },
  {
    id: "01BBB",
    label: { en: "Closing", ar: "خاتمة" },
    body: { en: "Thanks for writing", ar: "شكرا" },
  },
  {
    id: "01CCC",
    label: { en: "Escalation", ar: "تصعيد" },
    body: { en: "Passing this on", ar: "سأحوله" },
  },
];

function setup(overrides: Partial<React.ComponentProps<typeof QuickReplyList>> = {}) {
  const props = {
    replies: REPLIES,
    onReorder: vi.fn(),
    onEdit: vi.fn(),
    onDelete: vi.fn(),
    ...overrides,
  };

  render(<QuickReplyList {...props} />);

  return props;
}

describe("QuickReplyList reorders from the keyboard", () => {
  it("offers buttons, not a drag handle", () => {
    setup();

    // A drag handle is unreachable without a pointer. The buttons ARE the
    // mechanism here, not an accessible consolation prize bolted on afterwards.
    expect(screen.getAllByRole("button", { name: /Move down/ }).length).toBeGreaterThan(0);
    expect(screen.queryByRole("button", { name: /drag/i })).not.toBeInTheDocument();
  });

  it("sends the COMPLETE new order, not just the moved id", async () => {
    const { onReorder } = setup();

    await userEvent.click(screen.getByRole("button", { name: "Move down: Greeting" }));

    // A partial list would be read by the server as "delete the rest".
    expect(onReorder).toHaveBeenCalledWith(["01BBB", "01AAA", "01CCC"]);
  });

  it("moves a reply up", async () => {
    const { onReorder } = setup();

    await userEvent.click(screen.getByRole("button", { name: "Move up: Escalation" }));

    expect(onReorder).toHaveBeenCalledWith(["01AAA", "01CCC", "01BBB"]);
  });

  it("cannot move the first row up or the last row down", () => {
    setup();

    expect(screen.getByRole("button", { name: "Move up: Greeting" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "Move down: Escalation" })).toBeDisabled();
    // ...and the ones in the middle stay available.
    expect(screen.getByRole("button", { name: "Move up: Closing" })).toBeEnabled();
  });

  it("announces the new position through a live region", async () => {
    setup();

    await userEvent.click(screen.getByRole("button", { name: "Move down: Greeting" }));

    // "It moved" is invisible to a reader who cannot see the list jump.
    const status = screen.getByRole("status");
    expect(status).toHaveTextContent("Moved Greeting to position 2 of 3.");
    expect(status).toHaveAttribute("aria-live", "polite");
  });

  it("keeps focus on the button that was pressed", async () => {
    const button = () => screen.getByRole("button", { name: "Move down: Greeting" });

    setup();
    await userEvent.click(button());

    // Otherwise focus falls to <body> and a keyboard user has to tab all the
    // way back in to press it a second time.
    await waitFor(() => expect(button()).toHaveFocus());
  });

  it("names the row in every action's accessible name", () => {
    setup();

    // "Delete" alone tells a screen-reader user nothing about which row.
    expect(screen.getByRole("button", { name: "Delete: Closing" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Edit: Closing" })).toBeInTheDocument();
  });

  it("routes edit and delete to the right reply", async () => {
    const { onEdit, onDelete } = setup();

    await userEvent.click(screen.getByRole("button", { name: "Edit: Closing" }));
    expect(onEdit).toHaveBeenCalledWith(REPLIES[1]);

    await userEvent.click(screen.getByRole("button", { name: "Delete: Escalation" }));
    expect(onDelete).toHaveBeenCalledWith(REPLIES[2]);
  });

  it("isolates the Latin preview so Arabic prose cannot reorder it", () => {
    setup();

    const list = screen.getByRole("list", { name: /Quick replies/ });
    const preview = within(list).getByText("Hello there");

    // Without dir="ltr" isolation this reorders, silently, and only in Arabic.
    expect(preview.closest("bdi")).not.toBeNull();
  });

  it("shows an empty state rather than a bare list", () => {
    setup({ replies: [] });

    expect(screen.getByText("No quick replies yet")).toBeInTheDocument();
    expect(screen.queryByRole("list")).not.toBeInTheDocument();
  });

  it("offers the add affordance in both the empty and populated states", () => {
    const onAdd = vi.fn();

    const { unmount } = render(
      <QuickReplyList
        replies={[]}
        onReorder={vi.fn()}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
        onAdd={onAdd}
      />,
    );
    expect(screen.getByRole("button", { name: "Add quick reply" })).toBeInTheDocument();
    unmount();

    render(
      <QuickReplyList
        replies={REPLIES}
        onReorder={vi.fn()}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
        onAdd={onAdd}
      />,
    );
    expect(screen.getByRole("button", { name: "Add quick reply" })).toBeInTheDocument();
  });
});
