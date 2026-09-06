import { render, screen } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { AgentHomeScreen } from "@/components/screens/home/AgentHomeScreen";
import en from "@/messages/en.json";

const listTickets = vi.fn();
const ticketCounts = vi.fn();
const listTasks = vi.fn();
const listMentions = vi.fn();
const setTaskCompletion = vi.fn();
const chatDesk = vi.fn();

vi.mock("@/lib/api/tickets", async (importOriginal) => ({
  // The real module for everything else — `ticketListQuery` builds the counts
  // strip's links, and stubbing it would test a strip that links nowhere.
  ...(await importOriginal<typeof import("@/lib/api/tickets")>()),
  listTickets: (...a: unknown[]) => listTickets(...a),
  ticketCounts: (...a: unknown[]) => ticketCounts(...a),
}));

vi.mock("@/lib/api/chat", () => ({
  chatDesk: () => chatDesk(),
  takeChat: vi.fn(),
}));

vi.mock("@/lib/api/personal", () => ({
  listTasks: (...a: unknown[]) => listTasks(...a),
  listMentions: (...a: unknown[]) => listMentions(...a),
  createTask: vi.fn(),
  markMentionRead: vi.fn(),
  setTaskCompletion: (...a: unknown[]) => setTaskCompletion(...a),
}));

const COUNTS = {
  assigned_to_me: 3,
  unassigned: 7,
  at_risk: null,
  breached: null,
  pending_customer_reply: 2,
  personal: { tasks: 4, tasks_overdue: 1, mentions: 1 },
};

/**
 * Home's three tabs.
 *
 * The settled IA decision this covers: tasks and reminders are a TAB, not a
 * destination. No route of their own, no sidebar entry, no global task list —
 * a Tasks section beside Tickets invites a second backlog with its own queue,
 * its own owner and its own arguments about priority.
 */
describe("Home's tabs", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    listTickets.mockResolvedValue({ data: [], included: {} });
    ticketCounts.mockResolvedValue(COUNTS);
    listTasks.mockResolvedValue([
      {
        id: "01JQZ0000000000000000000AA",
        title: "Call Najd Logistics",
        due_at: null,
        completed_at: null,
        overdue: false,
        ticket_id: null,
        ticket_reference: null,
        ticket_subject: null,
      },
    ]);
    listMentions.mockResolvedValue([]);
    chatDesk.mockResolvedValue({ poll_seconds: 3, waiting: [], mine: [] });
  });

  it("shows four tabs and lands on the queue", async () => {
    render(<AgentHomeScreen currentUserId={7} onOpen={vi.fn()} />);

    const tabs = await screen.findAllByRole("tab");

    // The queue, tasks and reminders, live chat, mentions. All of them panels
    // of ONE screen — none of them is a destination with a route of its own.
    expect(tabs).toHaveLength(4);
    expect(tabs[0]).toHaveAttribute("aria-selected", "true");
  });

  it("survives a chat response that arrives without its lists", async () => {
    // TypeScript says `waiting` is always there; a deployment where the API is
    // a version behind says otherwise — and reading `.length` off it blanked
    // the entire screen, queue and counts included, rather than one tab.
    chatDesk.mockResolvedValue({ poll_seconds: 3 });

    render(<AgentHomeScreen currentUserId={7} onOpen={vi.fn()} />);

    expect(await screen.findByRole("heading", { name: en.home.title })).toBeInTheDocument();
    expect(await screen.findAllByRole("tab")).toHaveLength(4);
  });

  it("puts the badge inside the tab's own name", async () => {
    render(<AgentHomeScreen currentUserId={7} onOpen={vi.fn()} />);

    /*
     * Announced as "Tasks and reminders, 4" rather than a bare number read out
     * after the tab name — which is what a badge in its own element does.
     */
    expect(
      await screen.findByRole("tab", { name: en.home.tabs.tasks.replace("{count}", "4") }),
    ).toBeInTheDocument();

    expect(
      screen.getByRole("tab", { name: en.home.tabs.mentions.replace("{count}", "1") }),
    ).toBeInTheDocument();
  });

  it("reads its badges from the counts strip's own response", async () => {
    render(<AgentHomeScreen currentUserId={7} onOpen={vi.fn()} />);

    await screen.findAllByRole("tab");

    /*
     * One request for the strip AND both badges. Home refreshes every thirty
     * seconds and is the busiest screen in the product; a badge that fetched
     * itself would be a third request that could disagree with the list it
     * counts.
     */
    expect(ticketCounts).toHaveBeenCalledTimes(1);
  });

  it("opens the tasks tab when the address bar asks for it", async () => {
    render(<AgentHomeScreen currentUserId={7} onOpen={vi.fn()} initialTab="tasks" />);

    // Where a reminder on a standalone task lands.
    expect(await screen.findByText("Call Najd Logistics")).toBeInTheDocument();
  });

  it("ticks a task off from the tab", async () => {
    setTaskCompletion.mockResolvedValue({});

    render(<AgentHomeScreen currentUserId={7} onOpen={vi.fn()} initialTab="tasks" />);

    await screen.findByText("Call Najd Logistics");
    await userEvent.click(screen.getByRole("checkbox"));

    expect(setTaskCompletion).toHaveBeenCalledWith("01JQZ0000000000000000000AA", true);
  });

  it("switches to mentions without leaving Home", async () => {
    render(<AgentHomeScreen currentUserId={7} onOpen={vi.fn()} />);

    const tab = await screen.findByRole("tab", {
      name: en.home.tabs.mentions.replace("{count}", "1"),
    });

    await userEvent.click(tab);

    // Same screen, same counts strip above it. Not a route, not a destination.
    expect(screen.getByText(en.home.mentions.empty)).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: en.home.title })).toBeInTheDocument();
  });
});
