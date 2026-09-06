import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/admin/integrations",
}));

import { ExchangeLogTable } from "@/components/domain/ExchangeLogTable/ExchangeLogTable";

const ROWS = [
  {
    id: "01E1",
    direction: "outbound",
    integration: "erp",
    target: "https://erp.example.test/api/customers",
    status: "abandoned",
    attempt: 4,
    response_status: null,
    duration_ms: 15_000,
    error: "Connection refused",
    occurred_at: "2026-10-03T09:00:00Z",
  },
  {
    id: "01E2",
    direction: "outbound",
    integration: "email",
    target: "hana@example.test",
    status: "succeeded",
    attempt: 1,
    response_status: null,
    duration_ms: 240,
    error: null,
    occurred_at: "2026-10-03T08:00:00Z",
  },
];

beforeEach(() => {
  vi.stubGlobal(
    "fetch",
    vi.fn(async () => new Response(JSON.stringify({ data: ROWS }), { status: 200 })),
  );
});

describe("ExchangeLogTable", () => {
  it("shows every integration in one log", async () => {
    render(<ExchangeLogTable />);

    /*
     * The point of the story's sixth criterion, made visible: mail and the ERP
     * are rows in the same table. An administrator asking "did anything reach
     * the outside world last night?" asks once.
     */
    await waitFor(() => expect(screen.getByText("erp")).toBeInTheDocument());
    expect(screen.getByText("email")).toBeInTheDocument();
  });

  it("says a run gave up rather than calling it one more failure", async () => {
    render(<ExchangeLogTable />);

    /*
     * `abandoned` is the row that means the queue has stopped retrying and
     * this now needs a person. Rendered as "failed" it would be
     * indistinguishable from the three attempts before it — which is the one
     * thing the reader is trying to work out.
     */
    await waitFor(() => expect(screen.getByText("Gave up")).toBeInTheDocument());
    expect(screen.getByText("Succeeded")).toBeInTheDocument();
  });

  it("shows the endpoint and the provider's own error, not a generic one", async () => {
    render(<ExchangeLogTable />);

    await waitFor(() =>
      expect(screen.getByText("https://erp.example.test/api/customers")).toBeInTheDocument(),
    );

    // "Failed" on its own is a result an administrator can do nothing with.
    expect(screen.getByText("Connection refused")).toBeInTheDocument();
  });

  it("filters to the rows somebody is actually looking for", async () => {
    render(<ExchangeLogTable />);

    await waitFor(() => expect(screen.getByText("email")).toBeInTheDocument());

    await userEvent.click(screen.getByRole("button", { name: "Problems only" }));

    // The abandoned exchange stays; the successful send goes.
    expect(screen.getByText("erp")).toBeInTheDocument();
    expect(screen.queryByText("email")).not.toBeInTheDocument();
  });

  it("never renders a column that could carry a credential", async () => {
    render(<ExchangeLogTable />);

    await waitFor(() => expect(screen.getByText("erp")).toBeInTheDocument());

    /*
     * The log holds a redacted request payload for diagnosis, but the console
     * does not put it on screen next to eight other rows. Redaction is the
     * guarantee; not rendering it is the belt.
     */
    expect(screen.queryByText(/Authorization/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/Bearer /i)).not.toBeInTheDocument();
  });
});
