import { render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/tickets/01T1",
}));

import { withIntl } from "@/__tests__/helpers/intl";
import { TicketDetailScreen } from "@/components/screens/tickets/TicketDetailScreen";
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
  version: 3,
  created_at: "2026-09-01T08:00:00Z",
  updated_at: "2026-09-02T08:00:00Z",
};

const CONTEXT = {
  customer_id: "01C1",
  reference: "C-9XQ4TR2M",
  full_name: "Hana Yousef",
  state: "active",
  department: { id: 2, name: "Billing" },
  open_ticket_count: 3,
  recent_ticket_count: 5,
  recent_window_days: 30,
  last_interaction_at: "2026-09-02T09:00:00Z",
};

const MESSAGES = [
  {
    id: "01M1",
    ticket_id: "01T1",
    direction: "internal",
    author: { type: "staff", id: "7", name: "Dana Faris" },
    body: "Check the billing run.",
    sent_at: "2026-09-02T09:31:00Z",
    delivery_state: null,
    attachments: [],
  },
];

function json(body: unknown) {
  return new Response(JSON.stringify(body), {
    status: 200,
    headers: { "Content-Type": "application/json" },
  });
}

beforeEach(() => {
  localStorage.clear();

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);

      if (url.includes("/customer-context")) return json(CONTEXT);
      if (url.includes("/messages")) return json({ data: MESSAGES });
      if (url.includes("/quick-replies")) return json({ data: [] });

      return json(TICKET);
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

describe.each(DIRECTIONS)("the ticket workspace · dir=$dir", ({ dir, locale }) => {
  it("has no WCAG 2.1 AA violations once loaded", async () => {
    const host = document.createElement("div");
    host.setAttribute("dir", dir);
    host.setAttribute("lang", locale);
    document.body.appendChild(host);

    const { container } = render(
      withIntl(
        <TicketDetailScreen
          ticketId="01T1"
          categories={[{ id: 1, name: "Billing" }]}
          assignees={[{ id: 7, name: "Dana Faris" }]}
          departments={[{ id: 2, name: "Support" }]}
          editable
          onNavigate={vi.fn()}
        />,
        locale,
      ),
      { container: host },
    );

    // The loaded state is the interesting one: three regions, a live
    // conversation, and a composer that has to stay reachable in both
    // directions.
    await screen.findByRole("heading", { name: /Duplicate charge/ });

    expect(await axe(container)).toHaveNoViolations();
  });
});
