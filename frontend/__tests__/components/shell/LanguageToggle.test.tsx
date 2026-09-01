import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const refresh = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh, push: vi.fn() }),
  usePathname: () => "/",
}));

import { LanguageToggle } from "@/components/shell/LanguageToggle";

import { ar, en, withIntl } from "./helpers";

beforeEach(() => {
  refresh.mockClear();
});

afterEach(() => {
  vi.unstubAllGlobals();
});

function mockFetch(ok: boolean) {
  const fetchMock = vi.fn(async () =>
    ok ? new Response(JSON.stringify({ ok: true }), { status: 200 }) : new Response(null, { status: 500 }),
  );
  vi.stubGlobal("fetch", fetchMock);
  return fetchMock;
}

describe("LanguageToggle", () => {
  it("offers Arabic while English is active", () => {
    render(withIntl(<LanguageToggle />));

    // The label names the language you switch TO — a control named after the
    // current state reads as a status rather than an action.
    expect(screen.getByRole("button", { name: en.shell.actions.toggleLanguage })).toBeInTheDocument();
  });

  it("offers English while Arabic is active", () => {
    render(withIntl(<LanguageToggle />, "ar"));

    expect(screen.getByRole("button", { name: ar.shell.actions.toggleLanguage })).toBeInTheDocument();
  });

  it("persists the other locale server-side and refreshes", async () => {
    const fetchMock = mockFetch(true);
    const user = userEvent.setup();

    render(withIntl(<LanguageToggle />));
    await user.click(screen.getByTestId("language-toggle"));

    await waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());

    const [url, init] = fetchMock.mock.calls[0] as unknown as [string, RequestInit];
    expect(url).toBe("/api/locale");
    expect(init.method).toBe("POST");
    expect(JSON.parse(init.body as string)).toEqual({ locale: "ar" });

    // dir/lang live on <html> in the root layout, so only a server round trip
    // can change them coherently.
    await waitFor(() => expect(refresh).toHaveBeenCalled());
  });

  it("switches back to English from Arabic", async () => {
    const fetchMock = mockFetch(true);
    const user = userEvent.setup();

    render(withIntl(<LanguageToggle />, "ar"));
    await user.click(screen.getByTestId("language-toggle"));

    await waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());
    const [, init] = fetchMock.mock.calls[0] as unknown as [string, RequestInit];
    expect(JSON.parse(init.body as string)).toEqual({ locale: "en" });
  });

  it("surfaces an error and does not refresh when the request fails", async () => {
    mockFetch(false);
    const onError = vi.fn();
    const user = userEvent.setup();

    render(withIntl(<LanguageToggle onError={onError} />));
    await user.click(screen.getByTestId("language-toggle"));

    // Nothing changed, so the control must not look like it worked.
    await waitFor(() => expect(onError).toHaveBeenCalledWith(en.errors.localeSwitchFailed));
    expect(refresh).not.toHaveBeenCalled();
  });

  it("reports the failure in Arabic when Arabic is active", async () => {
    mockFetch(false);
    const onError = vi.fn();
    const user = userEvent.setup();

    render(withIntl(<LanguageToggle onError={onError} />, "ar"));
    await user.click(screen.getByTestId("language-toggle"));

    await waitFor(() => expect(onError).toHaveBeenCalledWith(ar.errors.localeSwitchFailed));
  });
});
