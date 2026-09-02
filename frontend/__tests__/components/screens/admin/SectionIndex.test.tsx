import { describe, expect, it, vi } from "vitest";

const pathname = vi.hoisted(() => ({ current: "/admin/ticketing" }));

vi.mock("next/navigation", () => ({
  usePathname: () => pathname.current,
}));

import { render, screen, ar, en } from "@/__tests__/helpers/intl";
import { SectionIndex } from "@/components/screens/admin/SectionIndex";
import { ADMIN_SECTIONS, SECTION_PATHS } from "@/components/screens/admin/sections";

describe("SectionIndex lists the configuration sections", () => {
  it("renders exactly the six sections in scope", () => {
    pathname.current = "/admin/ticketing";
    render(<SectionIndex />);

    const links = screen.getAllByRole("link");

    // Six, not "everything the product will eventually configure". An index
    // listing destinations that do not exist teaches the reader that half the
    // navigation is decorative.
    expect(links).toHaveLength(6);
    expect(links.map((link) => link.getAttribute("href"))).toEqual(
      ADMIN_SECTIONS.map((section) => SECTION_PATHS[section]),
    );
  });

  it("does not offer the sections that are out of scope", () => {
    pathname.current = "/admin/ticketing";
    render(<SectionIndex />);

    for (const absent of [/knowledge/i, /integration/i, /portal/i, /\bAI\b/]) {
      expect(screen.queryByRole("link", { name: absent })).toBeNull();
    }
  });

  it("marks the current section for assistive technology, not only visually", () => {
    pathname.current = "/admin/service-levels";
    render(<SectionIndex />);

    // A highlight alone is invisible to a screen-reader user.
    expect(screen.getByRole("link", { name: en.admin.sections.serviceLevels })).toHaveAttribute(
      "aria-current",
      "page",
    );
    expect(screen.getByRole("link", { name: en.admin.sections.ticketing })).not.toHaveAttribute(
      "aria-current",
    );
  });

  it("keeps the section current on its sub-paths", () => {
    pathname.current = "/admin/ticketing/categories";
    render(<SectionIndex />);

    expect(screen.getByRole("link", { name: en.admin.sections.ticketing })).toHaveAttribute(
      "aria-current",
      "page",
    );
  });

  it("does not mark a section current on a merely similar path", () => {
    // "/admin/email-templates" must not light up "/admin/email".
    pathname.current = "/admin/email-templates";
    render(<SectionIndex />);

    expect(screen.getByRole("link", { name: en.admin.sections.email })).not.toHaveAttribute(
      "aria-current",
    );
  });

  it("marks nothing current when the path is unknown", () => {
    pathname.current = "/admin";
    render(<SectionIndex />);

    expect(screen.queryByRole("link", { current: "page" })).toBeNull();
  });

  it("names the navigation so it can be jumped to", () => {
    pathname.current = "/admin/ticketing";
    render(<SectionIndex />);

    expect(screen.getByRole("navigation", { name: en.admin.indexLabel })).toBeInTheDocument();
  });

  it("renders in Arabic without falling back to the key path", () => {
    pathname.current = "/admin/ticketing";
    render(<SectionIndex />, { locale: "ar" });

    expect(screen.getByRole("link", { name: ar.admin.sections.serviceLevels })).toBeInTheDocument();
    expect(screen.queryByText(/admin\.sections/)).toBeNull();
  });
});
