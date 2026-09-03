import { render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn(), replace: vi.fn() }),
  usePathname: () => "/tickets",
}));

import { withIntl } from "@/__tests__/helpers/intl";
import { AgentHomeScreen } from "@/components/screens/home/AgentHomeScreen";
import { TicketListScreen } from "@/components/screens/tickets/TicketListScreen";
import type { Locale } from "@/lib/i18n/locale";

import { axe } from "./axe";

const DIRECTIONS: Array<{ dir: "ltr" | "rtl"; locale: Locale }> = [
  { dir: "ltr", locale: "en" },
  { dir: "rtl", locale: "ar" },
];

const TICKET = {
  id: "01T1",
  reference: "TKT-000042",
  subject: "Duplicate charge",
  description: "Charged twice.",
  customer_id: "01C1",
  channel: "agent",
  status: "open",
  priority: "normal",
  category_id: null,
  assignee_id: null,
  department_id: null,
  creator_type: "staff",
  creator_id: "7",
  version: 1,
  created_at: "2026-09-01T08:00:00Z",
  updated_at: "2026-09-02T08:00:00Z",
};

function json(body: unknown) {
  return new Response(JSON.stringify(body), {
    status: 200,
    headers: { "Content-Type": "application/json" },
  });
}

beforeEach(() => {
  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) =>
      String(input).includes("/counts")
        ? json({
            assigned_to_me: 3,
            unassigned: 5,
            at_risk: null,
            breached: null,
            pending_customer_reply: 1,
          })
        : json({
            data: [TICKET],
            meta: { total: 1, per_page: 25, current_page: 1, last_page: 1 },
          }),
    ),
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

describe.each(DIRECTIONS)("the ticket surfaces · dir=$dir", ({ dir, locale }) => {
  it("the list has no WCAG 2.1 AA violations", async () => {
    const { container } = renderIn(
      <TicketListScreen params={{}} onParamsChange={vi.fn()} onOpen={vi.fn()} />,
      dir,
      locale,
    );

    await screen.findByText("TKT-000042");

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the agent home has no violations", async () => {
    const { container } = renderIn(
      <AgentHomeScreen currentUserId={7} onOpen={vi.fn()} />,
      dir,
      locale,
    );

    // The loaded state: five links and a table, both of which have to stay
    // reachable in either writing direction.
    await screen.findByText("TKT-000042");

    expect(await axe(container)).toHaveNoViolations();
  });
});
