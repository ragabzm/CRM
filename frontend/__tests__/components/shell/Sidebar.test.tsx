import { render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

const pathname = { current: "/" };

vi.mock("next/navigation", () => ({
  usePathname: () => pathname.current,
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
}));

import { Sidebar } from "@/components/shell/Sidebar";

import { ar, en, withIntl } from "./helpers";

afterEach(() => {
  pathname.current = "/";
  vi.unstubAllEnvs();
});

describe("Sidebar destinations", () => {
  it("renders the four destinations for an administrator", () => {
    render(withIntl(<Sidebar />));

    for (const label of [
      en.shell.nav.home,
      en.shell.nav.tickets,
      en.shell.nav.customers,
      en.shell.nav.administration,
    ]) {
      expect(screen.getByRole("link", { name: label })).toBeInTheDocument();
    }
  });

  it("hides Administration from a non-administrator", () => {
    vi.stubEnv("NEXT_PUBLIC_STUB_ROLE", "agent");

    render(withIntl(<Sidebar />));

    // Absent rather than disabled: there is nothing for the reader to learn,
    // and advertising an unreachable destination invites the question.
    expect(screen.queryByRole("link", { name: en.shell.nav.administration })).toBeNull();
    expect(screen.getByRole("link", { name: en.shell.nav.tickets })).toBeInTheDocument();
  });

  it("marks the current destination with aria-current", () => {
    pathname.current = "/tickets";

    render(withIntl(<Sidebar />));

    expect(screen.getByRole("link", { name: en.shell.nav.tickets })).toHaveAttribute(
      "aria-current",
      "page",
    );
    expect(screen.getByRole("link", { name: en.shell.nav.home })).not.toHaveAttribute(
      "aria-current",
    );
  });

  it("keeps the section current on a nested route", () => {
    pathname.current = "/tickets/000123";

    render(withIntl(<Sidebar />));

    expect(screen.getByRole("link", { name: en.shell.nav.tickets })).toHaveAttribute(
      "aria-current",
      "page",
    );
  });

  it("does not mark Home current merely because every path starts with /", () => {
    pathname.current = "/customers";

    render(withIntl(<Sidebar />));

    expect(screen.getByRole("link", { name: en.shell.nav.home })).not.toHaveAttribute(
      "aria-current",
    );
  });

  it("renders every destination in Arabic when the locale is Arabic", () => {
    render(withIntl(<Sidebar />, "ar"));

    for (const label of [ar.shell.nav.tickets, ar.shell.nav.customers, ar.shell.nav.administration]) {
      expect(screen.getByRole("link", { name: label })).toBeInTheDocument();
    }
  });

  it("labels the navigation landmark", () => {
    render(withIntl(<Sidebar />));

    expect(screen.getByRole("navigation", { name: en.shell.nav.label })).toBeInTheDocument();
  });
});
