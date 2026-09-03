import { render, screen } from "@/__tests__/helpers/intl";
import { describe, expect, it } from "vitest";

import { SlaIndicator } from "@/components/domain/SlaIndicator/SlaIndicator";
import type { SlaBlock, SlaStateValue } from "@/lib/api/tickets";
import ar from "@/messages/ar.json";
import en from "@/messages/en.json";

/**
 * Where a ticket stands against its targets.
 *
 * The two states that are easy to get wrong are the ones these tests spend the
 * most on: `paused`, which is neither good nor bad, and `null`, which means
 * nobody is measuring — and must never render as "fine".
 */

function timer(state: SlaStateValue, remaining = 40) {
  return {
    state,
    elapsed_minutes: 20,
    target_minutes: 60,
    remaining_minutes: remaining,
    due_at: "2026-09-06T09:00:00Z",
  };
}

function block(state: SlaStateValue, overrides: Partial<SlaBlock> = {}): SlaBlock {
  return {
    state,
    response: timer(state),
    resolution: timer("on_track"),
    ...overrides,
  };
}

const badge = () => document.querySelector('[data-slot="sla-indicator"]')!;

describe("the SLA indicator", () => {
  it("names the state in words, not only in colour", () => {
    render(<SlaIndicator sla={block("at_risk")} />);

    // A red dot says nothing to a screen reader and nothing in greyscale, and
    // this is the badge an agent scans a list by.
    expect(screen.getByText(en.tickets.sla.state.at_risk)).toBeInTheDocument();
  });

  it("marks the state for styling", () => {
    render(<SlaIndicator sla={block("breached")} />);

    expect(badge()).toHaveAttribute("data-state", "breached");
  });

  it("draws running, paused and breached differently", () => {
    const classes = (["on_track", "paused", "breached"] as const).map((state) => {
      const { unmount } = render(<SlaIndicator sla={block(state)} />);
      const className = badge().className;
      unmount();

      return className;
    });

    // Three visually distinct treatments, which is what makes a list scannable
    // without reading every row.
    expect(new Set(classes).size).toBe(3);
  });

  it("counts down while a clock is running", () => {
    render(<SlaIndicator sla={block("on_track", { response: timer("on_track", 40) })} />);

    expect(screen.getByText(/40/)).toBeInTheDocument();
  });

  it("says how far past the target a breach is", () => {
    render(<SlaIndicator sla={block("breached", { response: timer("breached", -75) })} />);

    // "75 minutes over" is what a supervisor actually asks about.
    expect(screen.getByText(/75/)).toBeInTheDocument();
  });

  it("shows no countdown once a timer has stopped", () => {
    render(<SlaIndicator sla={block("met")} />);

    // "40 min left" on a finished ticket would be nonsense.
    expect(screen.queryByText(/40/)).toBeNull();
    expect(screen.getByText(en.tickets.sla.state.met)).toBeInTheDocument();
  });

  it("says a paused clock is stopped, and why", () => {
    render(<SlaIndicator sla={block("paused")} />);

    /*
     * Neither good nor bad, because it is neither: showing a
     * waiting-on-customer ticket as on track would put it in an agent's "act
     * on this" list for work that is not theirs to do.
     */
    expect(screen.getByText(en.tickets.sla.state.paused)).toBeInTheDocument();
    expect(badge()).toHaveAttribute("title", en.tickets.sla.pausedHint);
  });

  it("shows a dash when nothing is tracking", () => {
    render(<SlaIndicator sla={null} />);

    /*
     * Never "on track". A deployment with the engine off knows nothing about
     * its targets, and a green badge would be a claim it cannot support — the
     * same reason the counts strip shows a dash rather than a zero.
     */
    expect(badge()).toHaveAttribute("data-state", "not-tracked");
    expect(screen.queryByText(en.tickets.sla.state.on_track)).toBeNull();
  });

  it("says out loud that an untracked ticket is untracked", () => {
    render(<SlaIndicator sla={null} />);

    // A bare dash reads as missing data to a screen reader.
    expect(screen.getByText(en.tickets.sla.notTracked)).toBeInTheDocument();
  });

  it("shows both timers on the ticket's own screen", () => {
    render(<SlaIndicator sla={block("at_risk")} variant="full" />);

    // The rail has room to say which promise is at risk.
    expect(screen.getByText(en.tickets.sla.response)).toBeInTheDocument();
    expect(screen.getByText(en.tickets.sla.resolution)).toBeInTheDocument();
  });

  it("keeps the countdown readable in Arabic", () => {
    render(<SlaIndicator sla={block("on_track", { response: timer("on_track", 40) })} />, {
      locale: "ar",
    });

    // The Arabic copy, not the English.
    expect(screen.getByText(ar.tickets.sla.state.on_track)).toBeInTheDocument();

    // A duration is a figure: isolated so it does not reverse inside Arabic
    // prose.
    expect(document.querySelector('[data-slot="sla-indicator"] .num')).toHaveAttribute(
      "dir",
      "ltr",
    );
  });
});
