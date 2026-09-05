import { render, screen } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { TaskList } from "@/components/domain/TaskList/TaskList";
import type { PersonalTask } from "@/lib/api/personal";
import en from "@/messages/en.json";

function task(overrides: Partial<PersonalTask> = {}): PersonalTask {
  return {
    id: "01JQZ0000000000000000000AA",
    title: "Call Najd Logistics",
    due_at: null,
    completed_at: null,
    overdue: false,
    ticket_id: null,
    ticket_reference: null,
    ticket_subject: null,
    ...overrides,
  };
}

/**
 * An agent's own list.
 *
 * The behaviour worth testing is what the row REFUSES to do: it never
 * disappears because it is late, and it never hides the fact behind a colour.
 */
describe("the task list", () => {
  it("ticks a task off without deleting it", async () => {
    const onToggle = vi.fn().mockResolvedValue(undefined);

    render(<TaskList tasks={[task()]} onToggle={onToggle} onOpenTicket={vi.fn()} />);

    await userEvent.click(screen.getByRole("checkbox"));

    expect(onToggle).toHaveBeenCalledWith("01JQZ0000000000000000000AA", true);
    // Still on screen. Completion is a state — the row is the evidence the
    // work happened.
    expect(screen.getByText("Call Najd Logistics")).toBeInTheDocument();
  });

  it("un-ticks a completed one", async () => {
    const onToggle = vi.fn().mockResolvedValue(undefined);

    render(
      <TaskList
        tasks={[task({ completed_at: "2026-09-01T10:00:00Z" })]}
        onToggle={onToggle}
        onOpenTicket={vi.fn()}
      />,
    );

    await userEvent.click(screen.getByRole("checkbox"));

    expect(onToggle).toHaveBeenCalledWith("01JQZ0000000000000000000AA", false);
  });

  it("says an overdue task is overdue, in words", () => {
    const { container } = render(
      <TaskList
        tasks={[task({ overdue: true, due_at: "2026-08-24T15:00:00Z" })]}
        onToggle={vi.fn()}
        onOpenTicket={vi.fn()}
      />,
    );

    /*
     * The WORD, not a tint. UX-03 asks every state to survive greyscale, and
     * "late" is the one cue on this screen somebody must not miss.
     */
    expect(screen.getByText(new RegExp(en.home.tasks.overdue))).toBeInTheDocument();
    expect(container.querySelector("[data-slot='task-row']")).toHaveAttribute(
      "data-overdue",
      "true",
    );
  });

  it("keeps an overdue task where it is rather than dismissing it", () => {
    render(
      <TaskList
        tasks={[task({ overdue: true, due_at: "2026-08-24T15:00:00Z" })]}
        onToggle={vi.fn()}
        onOpenTicket={vi.fn()}
      />,
    );

    // BR-4.3: it stays visible and flagged until it is ticked off. The whole
    // value of writing it down is that it is still there when you forgot it.
    expect(screen.getByText("Call Najd Logistics")).toBeInTheDocument();
  });

  it("says so when a task is attached to nothing", () => {
    render(<TaskList tasks={[task()]} onToggle={vi.fn()} onOpenTicket={vi.fn()} />);

    // Otherwise an unattached task reads as one whose ticket failed to load.
    expect(screen.getByText(en.home.tasks.standalone)).toBeInTheDocument();
  });

  it("opens the ticket a task belongs to", async () => {
    const onOpenTicket = vi.fn();

    render(
      <TaskList
        tasks={[
          task({
            ticket_id: "01JQZ0000000000000000000BB",
            ticket_reference: "TKT-000412",
            ticket_subject: "Delivery never arrived",
          }),
        ]}
        onToggle={vi.fn()}
        onOpenTicket={onOpenTicket}
      />,
    );

    // The reference and subject travel with the row, so the agent knows what
    // they are about to open.
    expect(screen.getByText("Delivery never arrived")).toBeInTheDocument();

    await userEvent.click(screen.getByRole("button", { name: "TKT-000412" }));
    expect(onOpenTicket).toHaveBeenCalledWith("01JQZ0000000000000000000BB");
  });

  it("offers nothing to fill in beyond a title", () => {
    const { container } = render(
      <TaskList tasks={[task()]} onToggle={vi.fn()} onOpenTicket={vi.fn()} />,
    );

    /*
     * No description field, no assignee select, no sub-task control. Each is
     * the first step towards a project tracker inside a helpdesk.
     */
    expect(container.querySelectorAll("textarea")).toHaveLength(0);
    expect(container.querySelectorAll("select")).toHaveLength(0);
  });
});
