import { render, screen } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { ReportsScreen } from "@/components/screens/reports/ReportsScreen";
import type { ReportSet } from "@/lib/api/reports";
import en from "@/messages/en.json";

const fetchReports = vi.fn();

vi.mock("@/lib/api/reports", () => ({
  fetchReports: (...args: unknown[]) => fetchReports(...args),
}));

const PERIOD = { from: "2026-03-01", to: "2026-03-31" };

function reportSet(overrides: Partial<ReportSet> = {}): ReportSet {
  return {
    period: PERIOD,
    volume: {
      cards: [
        {
          key: "total",
          value: 12,
          filters: { created_from: "2026-03-01", created_to: "2026-03-31" },
        },
        {
          key: "open",
          value: 5,
          filters: { created_from: "2026-03-01", created_to: "2026-03-31", status: "open" },
        },
        { key: "pending", value: 2, filters: { status: "pending" } },
        { key: "resolved", value: 3, filters: { status: "resolved" } },
        { key: "closed", value: 2, filters: { status: "closed" } },
      ],
      by_status: [{ key: "open", label: "open", value: 5, filters: { status: "open" } }],
      by_category: [
        { key: "none", label: null, value: 4, filters: {} },
        { key: "1", label: "Billing", value: 8, filters: { category_id: 1 } },
      ],
      by_assignee: [{ key: "unassigned", label: null, value: 4, filters: {} }],
    },
    sla: {
      tickets: 12,
      breaches: 3,
      response: {
        breaches: 2,
        compliance_rate: 0.8333,
        average_minutes: 42,
        measured: 10,
        filters: { sla_state: "breached" },
      },
      resolution: {
        breaches: 1,
        compliance_rate: 0.9167,
        average_minutes: 310,
        measured: 8,
        filters: { sla_state: "breached" },
      },
    },
    satisfaction: {
      answered: 6,
      positive: 5,
      negative: 1,
      positive_rate: 0.8333,
      filters: { satisfaction: "rated" },
      positive_filters: { satisfaction: "positive" },
      negative_filters: { satisfaction: "negative" },
    },
    ...overrides,
  };
}

/**
 * A small set of figures a supervisor can trust and click into.
 *
 * Most of what is tested here is refusal: no export anywhere, no filter but a
 * date range, and — the one that matters most — nothing rendered as a zero
 * when the honest answer is "we did not measure that".
 */
describe("the reports surface", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    fetchReports.mockResolvedValue(reportSet());
  });

  it("shows six figures over the chosen period", async () => {
    const { container } = render(<ReportsScreen onOpen={vi.fn()} initialPeriod={PERIOD} />);

    await screen.findByText(en.reports.cards.total);

    expect(container.querySelectorAll("[data-slot='report-card']")).toHaveLength(5);
  });

  it("opens the tickets behind a card with the filters that produced it", async () => {
    const onOpen = vi.fn();

    render(<ReportsScreen onOpen={onOpen} initialPeriod={PERIOD} />);

    await screen.findByText(en.reports.cards.open);
    await userEvent.click(screen.getByText(en.reports.cards.open));

    /*
     * The FILTERS the server sent, not a link the screen built from a label.
     * A figure nobody can audit is a figure nobody believes — and one whose
     * click-through was reconstructed is one that can silently disagree.
     */
    expect(onOpen).toHaveBeenCalledWith({
      created_from: "2026-03-01",
      created_to: "2026-03-31",
      status: "open",
    });
  });

  it("opens the tickets behind every satisfaction figure", async () => {
    const onOpen = vi.fn();

    const { container } = render(<ReportsScreen onOpen={onOpen} initialPeriod={PERIOD} />);

    await screen.findByText(en.reports.satisfaction.title);

    for (const slot of [
      "satisfaction-answered",
      "satisfaction-positive",
      "satisfaction-negative",
    ]) {
      const button = container.querySelector(`[data-slot='${slot}']`);

      expect(button, `${slot} has no click-through`).not.toBeNull();
      await userEvent.click(button as HTMLElement);
    }

    // A figure with no click-through does not ship.
    expect(onOpen).toHaveBeenCalledTimes(3);
    expect(onOpen).toHaveBeenCalledWith({ satisfaction: "positive" });
  });

  it("says nothing was measured rather than showing a perfect score", async () => {
    fetchReports.mockResolvedValue(
      reportSet({
        sla: {
          tickets: 0,
          breaches: 0,
          response: {
            breaches: 0,
            compliance_rate: null,
            average_minutes: null,
            measured: 0,
            filters: {},
          },
          resolution: {
            breaches: 0,
            compliance_rate: null,
            average_minutes: null,
            measured: 0,
            filters: {},
          },
        },
      }),
    );

    const { container } = render(<ReportsScreen onOpen={vi.fn()} initialPeriod={PERIOD} />);

    await screen.findByText(en.reports.sla.title);

    /*
     * A desk congratulating itself on a month it did not work is the single
     * most misleading thing this surface could show.
     */
    const meters = container.querySelectorAll("[data-slot='compliance-meter']");

    expect(meters).toHaveLength(2);

    for (const meter of meters) {
      expect(meter.getAttribute("data-measured")).toBe("false");
      expect(meter.textContent).not.toMatch(/100\s*%/);
    }
  });

  it("says nobody answered rather than nought per cent", async () => {
    fetchReports.mockResolvedValue(
      reportSet({
        satisfaction: {
          answered: 0,
          positive: 0,
          negative: 0,
          positive_rate: null,
          filters: {},
          positive_filters: {},
          negative_filters: {},
        },
      }),
    );

    render(<ReportsScreen onOpen={vi.fn()} initialPeriod={PERIOD} />);

    // Zero per cent would say every customer who replied was unhappy.
    expect(await screen.findByText(en.reports.satisfaction.nobodyAnswered)).toBeInTheDocument();
  });

  it("shows an unmistakable empty state rather than a grid of zeros", async () => {
    fetchReports.mockResolvedValue(
      reportSet({
        volume: {
          cards: [
            { key: "total", value: 0, filters: {} },
            { key: "open", value: 0, filters: {} },
            { key: "pending", value: 0, filters: {} },
            { key: "resolved", value: 0, filters: {} },
            { key: "closed", value: 0, filters: {} },
          ],
          by_status: [],
          by_category: [],
          by_assignee: [],
        },
      }),
    );

    const { container } = render(<ReportsScreen onOpen={vi.fn()} initialPeriod={PERIOD} />);

    await screen.findByText(en.reports.empty);

    // Zeros presented as data look like an answer, and somebody will quote them.
    expect(container.querySelectorAll("[data-slot='report-card']")).toHaveLength(0);
  });

  it("names an uncategorised row rather than leaving a blank cell", async () => {
    render(<ReportsScreen onOpen={vi.fn()} initialPeriod={PERIOD} />);

    // The caption appears twice by design — as a heading and as the table's
    // own `<caption>` for a screen reader — so this waits on the row instead.
    await screen.findByText(en.reports.breakdown.uncategorised);

    // A desk where a third of the month has no category has a problem this row
    // is the only place to see.
    expect(screen.getByText(en.reports.breakdown.uncategorised)).toBeInTheDocument();
    expect(screen.getByText(en.reports.breakdown.unassigned)).toBeInTheDocument();
  });

  it("refuses with a reason rather than an empty page", async () => {
    fetchReports.mockRejectedValue(Object.assign(new Error("forbidden"), { status: 403 }));

    const { container } = render(<ReportsScreen onOpen={vi.fn()} initialPeriod={PERIOD} />);

    expect(await screen.findByText(en.reports.forbidden)).toBeInTheDocument();
    expect(screen.getByText(en.reports.forbiddenBody)).toBeInTheDocument();

    /*
     * UX-07. A report set showing zeros because somebody lacks a capability
     * looks like an answer, and the answer is "your desk did nothing".
     */
    expect(container.querySelectorAll("[data-slot='report-card']")).toHaveLength(0);
  });

  it("offers a date range and no other filter", async () => {
    const { container } = render(<ReportsScreen onOpen={vi.fn()} initialPeriod={PERIOD} />);

    await screen.findByText(en.reports.cards.total);

    const inputs = [...container.querySelectorAll("input")];

    // Two dates. No department, channel, priority, agent, category or branch.
    expect(inputs).toHaveLength(2);
    expect(inputs.every((input) => input.type === "date")).toBe(true);
    expect(container.querySelectorAll("select")).toHaveLength(0);
  });

  it("offers no way to export anything", async () => {
    const { container } = render(<ReportsScreen onOpen={vi.fn()} initialPeriod={PERIOD} />);

    await screen.findByText(en.reports.cards.total);

    /*
     * No CSV, no PDF, no print view, no download control and no share link.
     * The whole export board is out of scope, and this is where it would come
     * back in.
     */
    expect(container.textContent).not.toMatch(/csv|pdf|export|download|print|share/i);
    expect(container.querySelectorAll("a[download]")).toHaveLength(0);
  });

  it("asks the server again when the range changes", async () => {
    render(<ReportsScreen onOpen={vi.fn()} initialPeriod={PERIOD} />);

    await screen.findByText(en.reports.cards.total);
    expect(fetchReports).toHaveBeenCalledTimes(1);

    const inputs = screen.getAllByDisplayValue(/2026-03/);

    await userEvent.clear(inputs[0] as HTMLElement);
    await userEvent.type(inputs[0] as HTMLElement, "2026-02-01");
    await userEvent.click(screen.getByRole("button", { name: en.reports.range.apply }));

    expect(fetchReports).toHaveBeenCalledTimes(2);
  });
});
