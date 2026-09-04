import { describe, expect, it } from "vitest";

import { en, render, screen } from "@/__tests__/helpers/intl";
import { AvatarChip } from "@/components/domain/AvatarChip/AvatarChip";
import { SlaIndicator } from "@/components/domain/SlaIndicator/SlaIndicator";
import { StatusBadge } from "@/components/domain/StatusBadge/StatusBadge";
import { UrgencyMeter } from "@/components/domain/UrgencyMeter/UrgencyMeter";

/**
 * The three signals a ticket row is scanned by.
 *
 * Status, priority and assignee were plain black text, so every value in the
 * row carried identical weight and an agent had to READ each one. The design's
 * rule — one badge per row — only works if there is a badge.
 *
 * Every case here also checks the WORD, not only the colour. A treatment that
 * exists purely as a hue is invisible in greyscale, to a colour-blind reader,
 * and to a screen reader — which between them cover most of the people this
 * matters to.
 */

describe("the status badge", () => {
  it("says the status in words", () => {
    render(<StatusBadge status="open" />);

    expect(screen.getByText(en.tickets.status.open)).toBeInTheDocument();
  });

  it("marks which status it is, for styling and for tests", () => {
    render(<StatusBadge status="pending" />);

    expect(document.querySelector('[data-slot="status-badge"]')).toHaveAttribute(
      "data-status",
      "pending",
    );
  });

  it("draws a shape as well as a colour", () => {
    render(<StatusBadge status="closed" />);

    /*
     * The dot. Green and amber backgrounds are the same grey in greyscale and
     * the same colour to a reader with deuteranopia; the dot plus the word are
     * what survive both.
     */
    expect(document.querySelector('[data-slot="status-badge"] svg')).toBeInTheDocument();
  });
});

describe("the urgency meter", () => {
  it("names the level rather than only drawing it", () => {
    render(<UrgencyMeter priority="urgent" />);

    expect(screen.getByText(en.tickets.priority.urgent)).toBeInTheDocument();
  });

  it("lights more bars as the level rises", () => {
    const { unmount } = render(<UrgencyMeter priority="low" />);
    const low = document.querySelectorAll('[data-slot="urgency-meter"] i.bg-urgency-low').length;
    unmount();

    render(<UrgencyMeter priority="high" />);
    const high = document.querySelectorAll('[data-slot="urgency-meter"] i.bg-urgency-high').length;

    expect(low).toBe(1);
    expect(high).toBe(3);
  });

  it("hides the bars from assistive technology", () => {
    render(<UrgencyMeter priority="normal" />);

    // Three unlabelled marks announce nothing useful; the word beside them is
    // the whole message.
    expect(
      document.querySelector('[data-slot="urgency-meter"] [aria-hidden="true"]'),
    ).toBeInTheDocument();
  });
});

describe("the avatar chip", () => {
  it("takes initials from the first and last name", () => {
    render(<AvatarChip name="Omar Adel Nasser" unassignedLabel="Unassigned" />);

    // Not "OAN": nobody recognises a colleague by their middle name.
    expect(screen.getByText("ON")).toBeInTheDocument();
  });

  it("keeps the name readable to a screen reader when only the circle shows", () => {
    render(<AvatarChip name="Hana Support" unassignedLabel="Unassigned" />);

    expect(screen.getByText("Hana Support")).toBeInTheDocument();
  });

  it("draws unassigned as a different shape, not only a different word", () => {
    render(<AvatarChip name={null} unassignedLabel="Unassigned" />);

    const chip = document.querySelector('[data-slot="avatar-chip"]');

    expect(chip).toHaveAttribute("data-assigned", "false");
    expect(chip?.querySelector(".border-dashed")).toBeInTheDocument();
  });
});

describe("the SLA cell", () => {
  const timer = (over: Partial<Record<string, unknown>> = {}) => ({
    state: "at_risk" as const,
    elapsed_minutes: 200,
    target_minutes: 240,
    remaining_minutes: 40,
    due_at: "2026-09-06T13:00:00Z",
    ...over,
  });

  it("draws a bar under a clock that is still running", () => {
    render(<SlaIndicator sla={{ state: "at_risk", response: timer(), resolution: timer() }} />);

    expect(document.querySelector('[data-slot="sla-meter"]')).toBeInTheDocument();
  });

  it("draws no bar once the clock has stopped", () => {
    const met = timer({ state: "met", remaining_minutes: 60 });

    render(<SlaIndicator sla={{ state: "met", response: met, resolution: met }} />);

    // A bar under "Met" invites the reader to measure something that is no
    // longer moving.
    expect(document.querySelector('[data-slot="sla-meter"]')).not.toBeInTheDocument();
  });

  it("shows how much of the target has gone, on the ticket's own screen", () => {
    render(
      <SlaIndicator
        variant="full"
        sla={{ state: "at_risk", response: timer(), resolution: timer() }}
      />,
    );

    // 200 of 240 minutes. The API has sent this since Story 5.3 and nothing
    // rendered it, so "At risk" was a colour with no argument behind it.
    expect(screen.getAllByText(/83% elapsed/).length).toBeGreaterThan(0);
  });

  it("still says nothing rather than something when the engine is off", () => {
    render(<SlaIndicator sla={null} />);

    expect(document.querySelector('[data-slot="sla-indicator"]')).toHaveAttribute(
      "data-state",
      "not-tracked",
    );
  });
});
