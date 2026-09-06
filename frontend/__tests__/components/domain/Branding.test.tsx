import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { BrandedSurface } from "@/components/domain/BrandedSurface/BrandedSurface";
import { BrandHeader } from "@/components/domain/BrandedSurface/BrandHeader";

const fetchBranding = vi.fn();

vi.mock("@/lib/api/branding", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api/branding")>()),
  fetchBranding: () => fetchBranding(),
}));

const BRANDED = {
  logo_url: "/api/v1/attachments/01JQZ0000000000000000000AA/download",
  primary_colour: "#1c2333",
  header: "Ragab Trading",
};

const UNBRANDED = { logo_url: null, primary_colour: null, header: null };

/**
 * Branding reaches four customer surfaces and alters presentation only.
 *
 * The tests that matter are about what it CANNOT do: it never adds or removes
 * a control, and an unbranded deployment renders a finished product rather
 * than a page with holes in it.
 */
describe("branding on a customer surface", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    fetchBranding.mockResolvedValue(BRANDED);
  });

  it("puts the colour on the layout root as a custom property", async () => {
    const { container } = render(
      <BrandedSurface>
        <p>Anything</p>
      </BrandedSurface>,
    );

    await waitFor(() => {
      const root = container.querySelector("[data-slot='branded-surface']") as HTMLElement;

      expect(root.style.getPropertyValue("--brand-primary")).toBe("#1c2333");
    });
  });

  it("sets no property at all when nothing is configured", async () => {
    fetchBranding.mockResolvedValue(UNBRANDED);

    const { container } = render(
      <BrandedSurface>
        <p>Anything</p>
      </BrandedSurface>,
    );

    const root = container.querySelector("[data-slot='branded-surface']") as HTMLElement;

    /*
     * Not an empty string. `--brand-primary: ""` makes every
     * `var(--brand-primary, fallback)` below resolve to nothing rather than
     * to its fallback — a brand that was never configured would blank the
     * surface instead of leaving it alone.
     */
    await waitFor(() => expect(root.getAttribute("data-branded")).toBe("false"));
    expect(root.style.getPropertyValue("--brand-primary")).toBe("");
  });

  it("renders a finished page when the brand cannot be loaded", async () => {
    fetchBranding.mockRejectedValue(new Error("offline"));

    render(
      <BrandedSurface>
        <p>The request form</p>
      </BrandedSurface>,
    );

    /*
     * Silent and complete. A customer waiting on a spinner because a logo
     * could not be fetched is a worse outcome than a page with no logo.
     */
    expect(screen.getByText("The request form")).toBeInTheDocument();
  });

  it("adds and removes no control", async () => {
    const { container: branded } = render(
      <BrandedSurface>
        <button type="button">Send</button>
      </BrandedSurface>,
    );

    fetchBranding.mockResolvedValue(UNBRANDED);

    const { container: plain } = render(
      <BrandedSurface>
        <button type="button">Send</button>
      </BrandedSurface>,
    );

    await waitFor(() =>
      expect(branded.querySelectorAll("button").length).toBe(
        plain.querySelectorAll("button").length,
      ),
    );

    // Branding is presentation. A brand that could change what a customer may
    // do would be a permission model wearing a colour picker.
    expect(branded.querySelectorAll("button")).toHaveLength(1);
  });
});

describe("the brand header", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("shows the logo with the header line as its alternative text", async () => {
    fetchBranding.mockResolvedValue(BRANDED);

    render(<BrandHeader fallback="Support" />);

    /*
     * A logo with an empty alt is a logo a screen reader announces as nothing
     * — and on the portal's only masthead that is the whole heading.
     */
    const logo = await screen.findByRole("img", { name: "Ragab Trading" });

    expect(logo).toHaveAttribute("src", BRANDED.logo_url);
  });

  it("falls back to the product's own name when there is no logo", async () => {
    fetchBranding.mockResolvedValue(UNBRANDED);

    render(<BrandHeader fallback="Support" />);

    // Not an empty box and not a placeholder graphic: a customer arriving at
    // an unbranded portal should see a finished page.
    await waitFor(() => expect(screen.getByText("Support")).toBeInTheDocument());
    expect(screen.queryByRole("img")).toBeNull();
  });
});
