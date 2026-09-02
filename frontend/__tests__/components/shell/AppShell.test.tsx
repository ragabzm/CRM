import { render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/",
}));

import { AppShell } from "@/components/shell/AppShell";

import { ar, en, withIntl } from "./helpers";

/** Physical CSS that would break when the document direction flips. */
const PHYSICAL_CSS =
  /(?:^|[\s;"{])(?:left|right|margin-left|margin-right|padding-left|padding-right)\s*:/;

/** Physical Tailwind utilities, as whole class tokens. */
const PHYSICAL_UTILITY = /(?:^|\s)(?:[\w[\]&>:._-]+:)*(?:ml|mr|pl|pr)-[\w./-]+(?=\s|"|$)/;

function renderShell(dir: "ltr" | "rtl", locale: "en" | "ar" = "en") {
  const wrapper = document.createElement("div");
  wrapper.setAttribute("dir", dir);
  document.body.appendChild(wrapper);

  return render(
    withIntl(
      <AppShell>
        <p>content</p>
      </AppShell>,
      locale,
    ),
    { container: wrapper },
  );
}

afterEach(() => {
  vi.unstubAllEnvs();
});

describe("AppShell composition", () => {
  it("renders the chrome and the page content", () => {
    renderShell("ltr");

    expect(screen.getByRole("navigation", { name: en.shell.nav.label })).toBeInTheDocument();
    expect(screen.getByRole("banner")).toBeInTheDocument();
    expect(screen.getByRole("main")).toHaveTextContent("content");
  });

  it("carries the brand, the language toggle, the bell and the user menu", () => {
    renderShell("ltr");

    expect(screen.getByText(en.shell.brand)).toBeInTheDocument();
    expect(screen.getByTestId("language-toggle")).toBeInTheDocument();
    expect(screen.getByTestId("notification-bell")).toBeInTheDocument();
    expect(screen.getByTestId("user-menu")).toBeInTheDocument();
  });

  it("translates the whole chrome into Arabic", () => {
    renderShell("rtl", "ar");

    expect(screen.getByText(ar.shell.brand)).toBeInTheDocument();
    expect(screen.getByRole("link", { name: ar.shell.nav.tickets })).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: ar.shell.actions.openNotifications }),
    ).toBeInTheDocument();
  });

  it("hides Administration from a non-administrator", () => {
    vi.stubEnv("NEXT_PUBLIC_STUB_ROLE", "agent");

    renderShell("ltr");

    expect(screen.queryByRole("link", { name: en.shell.nav.administration })).toBeNull();
  });
});

describe("AppShell mirrors without any mirroring code", () => {
  it("uses no physical-direction CSS or utilities", () => {
    const { container } = renderShell("rtl", "ar");

    expect(container.innerHTML).not.toMatch(PHYSICAL_CSS);
    expect(container.innerHTML).not.toMatch(PHYSICAL_UTILITY);
  });

  it("puts the sidebar in grid column 1 in both directions", () => {
    /*
     * The sidebar is column 1 in the source in both writing modes. The browser
     * places column 1 on the left in LTR and on the right in RTL — that flow is
     * the entire mirroring mechanism, and it is why there is no direction code
     * in this component to get wrong. jsdom performs no layout, so this asserts
     * the structural precondition rather than pixel placement.
     */
    for (const dir of ["ltr", "rtl"] as const) {
      const { container, unmount } = renderShell(dir);
      const shell = container.querySelector("[data-slot='app-shell']")!;

      expect(shell.className).toContain("tablet:grid-cols-[15rem_1fr]");
      expect(shell.firstElementChild).toBe(
        container.querySelector("[data-slot='app-shell-sidebar']"),
      );
      unmount();
    }
  });

  it("renders identical markup in both directions", () => {
    const normalise = (html: string) => html.replace(/radix-[_\w]+/g, "radix-id");

    const ltr = renderShell("ltr");
    const ltrHtml = normalise(ltr.container.innerHTML);
    ltr.unmount();

    const rtl = renderShell("rtl");
    const rtlHtml = normalise(rtl.container.innerHTML);

    // Nothing branches on direction: one switch on <html>, and CSS does the rest.
    expect(rtlHtml).toBe(ltrHtml);
  });
});
