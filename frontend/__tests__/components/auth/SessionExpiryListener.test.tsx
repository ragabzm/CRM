import { render, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const replace = vi.fn();
const pathname = { current: "/tickets/000123" };

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace, push: vi.fn(), refresh: vi.fn() }),
  usePathname: () => pathname.current,
}));

import { SessionExpiryListener } from "@/components/auth/SessionExpiryListener";
import { SESSION_EXPIRED_EVENT } from "@/lib/api/client";

const DRAFT_KEY = "composer-draft:t-1";
const DRAFT_VALUE = "Half-written reply the agent has not sent yet";

beforeEach(() => {
  replace.mockClear();
  pathname.current = "/tickets/000123";
  localStorage.setItem(DRAFT_KEY, DRAFT_VALUE);
});

afterEach(() => {
  localStorage.clear();
});

function expire() {
  window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT));
}

describe("an expired session returns the reader to sign-in", () => {
  it("redirects, carrying where they were", async () => {
    render(<SessionExpiryListener />);

    expire();

    await waitFor(() =>
      expect(replace).toHaveBeenCalledWith("/sign-in?redirect=%2Ftickets%2F000123"),
    );
  });

  it("preserves an unsent composer draft", async () => {
    render(<SessionExpiryListener />);

    expire();

    await waitFor(() => expect(replace).toHaveBeenCalled());

    /*
     * The load-bearing assertion. A session can lapse while someone is
     * mid-sentence; clearing storage on the way out would destroy work the
     * reader never chose to discard. There is nothing to clear for security
     * either — cookie mode puts no credential in web storage.
     */
    expect(localStorage.getItem(DRAFT_KEY)).toBe(DRAFT_VALUE);
  });

  it("clears nothing at all from web storage", async () => {
    const removeItem = vi.spyOn(Storage.prototype, "removeItem");
    const clear = vi.spyOn(Storage.prototype, "clear");

    render(<SessionExpiryListener />);
    expire();

    await waitFor(() => expect(replace).toHaveBeenCalled());

    expect(removeItem).not.toHaveBeenCalled();
    expect(clear).not.toHaveBeenCalled();

    removeItem.mockRestore();
    clear.mockRestore();
  });

  it("does not redirect when already on the sign-in route", async () => {
    pathname.current = "/sign-in?redirect=%2Ftickets";
    render(<SessionExpiryListener />);

    expire();

    // A second redirect would lose the original destination.
    await new Promise((resolve) => setTimeout(resolve, 20));
    expect(replace).not.toHaveBeenCalled();
  });

  it("stops listening once unmounted", async () => {
    const { unmount } = render(<SessionExpiryListener />);
    unmount();

    expire();

    await new Promise((resolve) => setTimeout(resolve, 20));
    expect(replace).not.toHaveBeenCalled();
  });

  it("renders nothing", () => {
    const { container } = render(<SessionExpiryListener />);

    expect(container).toBeEmptyDOMElement();
  });
});
