import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi, beforeEach } from "vitest";

import { en, render, screen, waitFor } from "@/__tests__/helpers/intl";
import { UserMenu } from "@/components/shell/UserMenu";

/**
 * Signing out actually signs you out.
 *
 * The menu item posted to `/api/sign-out` — a Next route handler whose entire
 * body was `return new NextResponse(null, { status: 204 })`, left behind a
 * TODO from Story 2.1. So it answered 204, `router.refresh()` re-rendered the
 * same authenticated page, and the Laravel session was never touched. The
 * screen stayed exactly where it was, still full of the person's tickets, and
 * `GET /api/v1/tickets` still answered 200.
 *
 * On a shared machine that is somebody walking away believing they are out
 * while the next person inherits their session.
 *
 * Nothing caught it because the stub returned a SUCCESS status, and no test
 * asked what the click actually did.
 */

const logout = vi.hoisted(() => vi.fn().mockResolvedValue(undefined));
const replace = vi.hoisted(() => vi.fn());

vi.mock("@/lib/auth/api", async () => {
  const actual = await vi.importActual<typeof import("@/lib/auth/api")>("@/lib/auth/api");

  return { ...actual, logout };
});

vi.mock("@/lib/auth/useCurrentUser", () => ({
  useCurrentUser: () => ({ displayName: "Hana Support", initials: "HS", loaded: true, id: "4" }),
}));

beforeEach(() => {
  logout.mockClear();
  replace.mockClear();

  Object.defineProperty(window, "location", {
    configurable: true,
    value: { ...window.location, replace },
  });
});

async function openMenu() {
  render(<UserMenu />);

  await userEvent.click(screen.getByTestId("user-menu"));

  return screen.findByRole("menuitem", { name: new RegExp(en.shell.actions.signOut, "i") });
}

describe("signing out", () => {
  it("calls the endpoint that ends the session", async () => {
    const item = await openMenu();

    await userEvent.click(item);

    // Not `/api/sign-out`, which was a stub answering 204 and doing nothing.
    await waitFor(() => expect(logout).toHaveBeenCalledTimes(1));
  });

  it("leaves the authenticated screen", async () => {
    const item = await openMenu();

    await userEvent.click(item);

    /*
     * A full document replace, not a soft navigation: the client is holding
     * the previous person's tickets, counts and notifications, and `replace`
     * keeps Back from returning to a screen full of somebody else's work.
     */
    await waitFor(() => expect(replace).toHaveBeenCalledWith("/sign-in"));
  });

  it("still leaves when the request fails", async () => {
    logout.mockRejectedValueOnce(new Error("offline"));

    const item = await openMenu();

    await userEvent.click(item);

    /*
     * The session may have survived, and the gate will send them back in —
     * which is the honest outcome. Staying put on a screen that said goodbye
     * would be worse than either.
     */
    await waitFor(() => expect(replace).toHaveBeenCalledWith("/sign-in"));
  });
});
