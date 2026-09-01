import { render, screen } from "@/__tests__/helpers/intl";
import { describe, expect, it } from "vitest";

import { BidiValue } from "@/components/domain/BidiValue/BidiValue";

const TICKET_REF = "TKT-000123";

describe("BidiValue isolates LTR runs", () => {
  it("renders a <bdi> by default", () => {
    const { container } = render(<BidiValue>{TICKET_REF}</BidiValue>);

    expect(container.querySelector("bdi")).not.toBeNull();
  });

  it("forces the run left-to-right", () => {
    const { container } = render(<BidiValue>{TICKET_REF}</BidiValue>);

    expect(container.querySelector("bdi")).toHaveAttribute("dir", "ltr");
  });

  it("carries the num utility, which applies unicode-bidi: isolate", () => {
    const { container } = render(<BidiValue>{TICKET_REF}</BidiValue>);

    // globals.css binds `.num` to direction:ltr + unicode-bidi:isolate +
    // tabular figures. Asserting the class keeps the rule in one place rather
    // than duplicating the declaration inline.
    expect(container.querySelector("bdi")!.className).toMatch(/(^|\s)num(\s|$)/);
  });

  it("keeps a Latin ticket reference in order inside Arabic prose", () => {
    render(
      <p>
        تم تعيين التذكرة <BidiValue>{TICKET_REF}</BidiValue> لك
      </p>,
    );

    // Without isolation the bidi algorithm reorders the neutral hyphen and the
    // run renders as 000123-TKT.
    expect(screen.getByText(TICKET_REF).textContent).toBe(TICKET_REF);
  });

  it("can render as a span where <bdi> is invalid content", () => {
    const { container } = render(<BidiValue as="span">{TICKET_REF}</BidiValue>);

    expect(container.querySelector("span")).toHaveAttribute("dir", "ltr");
    expect(container.querySelector("bdi")).toBeNull();
  });

  it("merges a caller class without dropping num", () => {
    const { container } = render(<BidiValue className="text-fg-muted">{TICKET_REF}</BidiValue>);

    const classes = container.querySelector("bdi")!.className;
    expect(classes).toMatch(/(^|\s)num(\s|$)/);
    expect(classes).toContain("text-fg-muted");
  });
});
