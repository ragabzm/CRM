import { render, screen } from "@/__tests__/helpers/intl";
import { describe, expect, it } from "vitest";

import { AiLabel } from "@/components/domain/AiLabel/AiLabel";
import ar from "@/messages/ar.json";
import en from "@/messages/en.json";

/**
 * Nothing a model wrote reaches a person without saying what it is.
 *
 * The label carries the rule this whole epic rests on: AI proposes, a PERSON
 * decides. An artefact presented without it reads as a decision already taken.
 */
describe("the AI label", () => {
  it("says both that it is AI and that it needs checking", () => {
    render(
      <AiLabel>
        <p>The customer was charged twice.</p>
      </AiLabel>,
    );

    expect(screen.getByText(en.ai.label)).toBeInTheDocument();
    expect(screen.getByText("The customer was charged twice.")).toBeInTheDocument();
  });

  it("labels the region so the caption is announced with the content", () => {
    render(
      <AiLabel>
        <p>A draft reply.</p>
      </AiLabel>,
    );

    // Not a stray sentence floating above the panel.
    expect(screen.getByRole("region", { name: en.ai.label })).toBeInTheDocument();
  });

  it("says it in words, not in a colour or an icon", () => {
    const { container } = render(
      <AiLabel>
        <p>A draft reply.</p>
      </AiLabel>,
    );

    /*
     * A tinted panel says nothing in greyscale, nothing to a screen reader and
     * nothing on a printed ticket — and this is the one caption that must
     * survive all three.
     */
    expect(container.querySelector("[data-slot='ai-label-text']")?.textContent).toBe(en.ai.label);
    expect(container.querySelectorAll("svg")).toHaveLength(0);
  });

  it("says it in Arabic too", () => {
    render(
      <AiLabel>
        <p>مسودة رد.</p>
      </AiLabel>,
      { locale: "ar" },
    );

    // A customer's agent reading in Arabic gets the warning in Arabic, or the
    // warning is not doing its job.
    expect(screen.getByText(ar.ai.label)).toBeInTheDocument();
  });
});
