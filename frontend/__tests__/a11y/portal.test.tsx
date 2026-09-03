import { render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), refresh: vi.fn() }),
  usePathname: () => "/portal/requests",
  useSearchParams: () => new URLSearchParams(),
}));

import { withIntl } from "@/__tests__/helpers/intl";
import { PortalNewRequest } from "@/components/screens/portal/PortalNewRequest";
import { PortalRegisterScreen } from "@/components/screens/portal/PortalRegisterScreen";
import { PortalRequestDetail } from "@/components/screens/portal/PortalRequestDetail";
import { PortalRequestList } from "@/components/screens/portal/PortalRequestList";
import { PortalSignInScreen } from "@/components/screens/portal/PortalSignInScreen";
import { PortalShell } from "@/components/shell/portal/PortalShell";
import type { Locale } from "@/lib/i18n/locale";

import { axe } from "./axe";

const DIRECTIONS: Array<{ dir: "ltr" | "rtl"; locale: Locale }> = [
  { dir: "ltr", locale: "en" },
  { dir: "rtl", locale: "ar" },
];

const DETAIL = {
  id: "01R1",
  reference: "TKT-000042",
  subject: "My invoice is wrong",
  status: "pending",
  created_at: "2026-09-01T08:00:00Z",
  updated_at: "2026-09-02T08:00:00Z",
  description: "I was charged twice.",
  messages: [
    {
      id: "01M1",
      from: "support",
      body: "Could you send the invoice number?",
      sent_at: "2026-09-02T09:00:00Z",
      attachments: [],
    },
  ],
};

beforeEach(() => {
  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      // A detail URL ends with an id; the list URL ends with `requests`.
      const detail = /\/requests\/[^/?]+$/.test(String(input));

      return new Response(JSON.stringify(detail ? DETAIL : { data: [DETAIL] }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

function renderIn(ui: React.ReactElement, dir: "ltr" | "rtl", locale: Locale) {
  const host = document.createElement("div");
  host.setAttribute("dir", dir);
  host.setAttribute("lang", locale);
  document.body.appendChild(host);

  return render(withIntl(ui, locale), { container: host });
}

describe.each(DIRECTIONS)("the portal · dir=$dir", ({ dir, locale }) => {
  it("the shell has no WCAG 2.1 AA violations", async () => {
    const { container } = renderIn(
      <PortalShell>
        <p>Content</p>
      </PortalShell>,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("sign-in has no violations", async () => {
    const { container } = renderIn(<PortalSignInScreen onSignedIn={vi.fn()} />, dir, locale);

    expect(await axe(container)).toHaveNoViolations();
  });

  it("registration has no violations", async () => {
    const { container } = renderIn(<PortalRegisterScreen onRegistered={vi.fn()} />, dir, locale);

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the request list has no violations", async () => {
    const { container } = renderIn(<PortalRequestList onOpen={vi.fn()} />, dir, locale);

    await screen.findByText("My invoice is wrong");

    expect(await axe(container)).toHaveNoViolations();
  });

  it("one request has no violations", async () => {
    const { container } = renderIn(<PortalRequestDetail requestId="01R1" />, dir, locale);

    await screen.findByText("Could you send the invoice number?");

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the new-request form has no violations", async () => {
    const { container } = renderIn(
      <PortalNewRequest customerId="01C1" onSubmitted={vi.fn()} />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("offers exactly three destinations", () => {
    renderIn(
      <PortalShell>
        <p>Content</p>
      </PortalShell>,
      dir,
      locale,
    );

    /*
     * Three, and no sidebar. A customer is not working a list — they came to
     * ask one question or check on one they already asked, usually on a phone.
     */
    const nav = screen.getByRole("navigation");

    expect(nav.querySelectorAll("a")).toHaveLength(3);
    expect(document.querySelector('[data-slot="app-shell-sidebar"]')).toBeNull();
  });

  it("hides the destinations before sign-in", () => {
    renderIn(
      <PortalShell signedIn={false}>
        <p>Content</p>
      </PortalShell>,
      dir,
      locale,
    );

    // Three links to pages that will bounce you back here are not navigation,
    // they are a maze.
    expect(screen.queryByRole("navigation")).toBeNull();
  });

  it("mirrors by writing direction rather than by mirroring code", () => {
    const { container } = renderIn(
      <PortalShell>
        <p>Content</p>
      </PortalShell>,
      dir,
      locale,
    );

    // Logical properties only, like the staff shell: no second stylesheet for
    // Arabic.
    expect(container.innerHTML).not.toMatch(/class="[^"]*\b(ml|mr|pl|pr|left|right)-/);
  });
});
