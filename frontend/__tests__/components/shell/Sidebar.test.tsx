import { render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

const pathname = { current: "/" };

vi.mock("next/navigation", () => ({
  usePathname: () => pathname.current,
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
}));

/**
 * The session the chrome reads.
 *
 * Mocked at the transport, not at the hook, so the narrowing the hook does —
 * an unknown role name is dropped rather than trusted — runs in these tests.
 */
const session = { roles: ["agent", "administrator"] as string[] };

vi.mock("@/lib/auth/api", () => ({
  me: vi.fn(async () => ({
    id: 1,
    name: "Hana Yousef",
    email: "hana@ragab.test",
    preferred_locale: "en",
    roles: session.roles,
  })),
}));

import { Sidebar } from "@/components/shell/Sidebar";

import { ar, en, withIntl } from "./helpers";

afterEach(() => {
  pathname.current = "/";
  session.roles = ["agent", "administrator"];
});

describe("Sidebar destinations", () => {
  it("renders the four destinations for an administrator", async () => {
    render(withIntl(<Sidebar />));

    expect(
      await screen.findByRole("link", { name: en.shell.nav.administration }),
    ).toBeInTheDocument();

    for (const label of [en.shell.nav.home, en.shell.nav.tickets, en.shell.nav.customers]) {
      expect(screen.getByRole("link", { name: label })).toBeInTheDocument();
    }
  });

  it("points Administration at the configuration console", async () => {
    render(withIntl(<Sidebar />));

    // /administration was never built. A destination in the chrome that 404s is
    // worse than one that is absent.
    expect(await screen.findByRole("link", { name: en.shell.nav.administration })).toHaveAttribute(
      "href",
      "/admin",
    );
  });

  it("hides Administration from a non-administrator", async () => {
    session.roles = ["agent"];

    render(withIntl(<Sidebar />));

    // The other destinations arrive, so this is a real absence rather than a
    // test that asserted before anything had rendered.
    await screen.findByRole("link", { name: en.shell.nav.tickets });

    // Absent rather than disabled: there is nothing for the reader to learn,
    // and advertising an unreachable destination invites the question.
    expect(screen.queryByRole("link", { name: en.shell.nav.administration })).toBeNull();
  });

  it("withholds the gated destination until the session has been read", () => {
    render(withIntl(<Sidebar />));

    // Synchronous assertion, before the fetch resolves. Showing it and taking
    // it away a moment later reads as a glitch.
    expect(screen.queryByRole("link", { name: en.shell.nav.administration })).toBeNull();
    expect(screen.getByRole("link", { name: en.shell.nav.tickets })).toBeInTheDocument();
  });

  it("ignores a role name it does not know", async () => {
    session.roles = ["agent", "superuser"];

    render(withIntl(<Sidebar />));
    await screen.findByRole("link", { name: en.shell.nav.tickets });

    expect(screen.queryByRole("link", { name: en.shell.nav.administration })).toBeNull();
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

  it("renders every destination in Arabic when the locale is Arabic", async () => {
    render(withIntl(<Sidebar />, "ar"));

    // The gated one last, once the session has arrived.
    expect(
      await screen.findByRole("link", { name: ar.shell.nav.administration }),
    ).toBeInTheDocument();

    for (const label of [ar.shell.nav.tickets, ar.shell.nav.customers]) {
      expect(screen.getByRole("link", { name: label })).toBeInTheDocument();
    }
  });

  it("labels the navigation landmark", () => {
    render(withIntl(<Sidebar />));

    expect(screen.getByRole("navigation", { name: en.shell.nav.label })).toBeInTheDocument();
  });
});
