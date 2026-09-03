import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { fireEvent } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const push = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push, replace: vi.fn(), refresh: vi.fn() }),
  usePathname: () => "/portal/sign-in",
  useSearchParams: () => new URLSearchParams("token=abc&email=hana%40example.test"),
}));

import {
  PortalForgotPasswordScreen,
  PortalResetPasswordScreen,
} from "@/components/screens/portal/PortalPasswordScreens";
import { PortalRegisterScreen } from "@/components/screens/portal/PortalRegisterScreen";
import { PortalSignInScreen } from "@/components/screens/portal/PortalSignInScreen";
import en from "@/messages/en.json";

let posts: Array<{ url: string; body: Record<string, unknown> }> = [];
let status = 200;
let detail: string | null = null;

function json(body: unknown, code = 200) {
  return new Response(JSON.stringify(body), {
    status: code,
    headers: { "Content-Type": code >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  posts = [];
  status = 200;
  detail = null;
  push.mockClear();

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);

      if (url.includes("/sanctum/csrf-cookie")) return new Response(null, { status: 204 });

      posts.push({ url, body: JSON.parse(String(init?.body ?? "{}")) });

      if (status !== 200) {
        return json({ status, code: "portal.invalid_credentials", detail }, status);
      }

      return json({ id: 1, name: "Hana", email: "hana@example.test", preferred_locale: "en" });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

describe("signing in to the portal", () => {
  it("sends what was typed", async () => {
    render(<PortalSignInScreen onSignedIn={vi.fn()} />);

    fireEvent.change(screen.getByLabelText(en.portal.auth.email), {
      target: { value: "hana@example.test" },
    });
    fireEvent.change(screen.getByLabelText(en.portal.auth.password), {
      target: { value: "a-long-enough-passphrase" },
    });

    await userEvent.click(screen.getByRole("button", { name: en.portal.auth.signIn }));

    await waitFor(() => expect(posts).toHaveLength(1));
    expect(posts[0]!.url).toContain("/portal/auth/login");
  });

  it("says one thing whether the address or the password was wrong", async () => {
    status = 401;
    render(<PortalSignInScreen onSignedIn={vi.fn()} />);

    await userEvent.click(screen.getByRole("button", { name: en.portal.auth.signIn }));

    /*
     * Matching the server. Telling them apart would turn this form into a way
     * to discover which addresses have accounts — which says who is a customer
     * of this business.
     */
    expect(await screen.findByRole("alert")).toHaveTextContent(en.portal.auth.error);
  });

  it("offers the way to a new account and to a reset", () => {
    render(<PortalSignInScreen onSignedIn={vi.fn()} />);

    // Somebody on this page who cannot get in has two problems, and both need
    // a door.
    expect(screen.getByRole("link", { name: en.portal.auth.forgot })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: en.portal.auth.register })).toBeInTheDocument();
  });
});

describe("registering", () => {
  it("sends the four fields and the language", async () => {
    render(<PortalRegisterScreen onRegistered={vi.fn()} />);

    for (const [label, value] of [
      [en.portal.auth.name, "Hana Yousef"],
      [en.portal.auth.email, "hana@example.test"],
      [en.portal.auth.password, "a-long-enough-passphrase"],
      [en.portal.auth.passwordConfirm, "a-long-enough-passphrase"],
    ] as const) {
      fireEvent.change(screen.getByLabelText(label), { target: { value } });
    }

    await userEvent.click(screen.getByRole("button", { name: "العربية" }));
    await userEvent.click(screen.getByRole("button", { name: en.portal.auth.register }));

    await waitFor(() => expect(posts).toHaveLength(1));

    // Asked, not inferred: the language somebody picks beats whatever their
    // browser advertises.
    expect(posts[0]!.body.preferred_locale).toBe("ar");
  });

  it("passes the server's own words through on a refusal", async () => {
    status = 422;
    detail = "An account already exists for this address.";

    render(<PortalRegisterScreen onRegistered={vi.fn()} />);
    await userEvent.click(screen.getByRole("button", { name: en.portal.auth.register }));

    // "Already exists" tells somebody exactly what to do next; a generic
    // failure leaves them retrying the same thing.
    expect(await screen.findByRole("alert")).toHaveTextContent("already exists");
  });
});

describe("recovering an account", () => {
  it("says the same thing whether or not the address is known", async () => {
    status = 200;
    render(<PortalForgotPasswordScreen />);

    await userEvent.click(screen.getByRole("button", { name: en.portal.auth.send }));

    expect(await screen.findByRole("status")).toHaveTextContent(en.portal.auth.forgotSent);
  });

  it("says the same thing even when the request failed", async () => {
    status = 500;
    render(<PortalForgotPasswordScreen />);

    await userEvent.click(screen.getByRole("button", { name: en.portal.auth.send }));

    // A visible failure would leak that this address was special.
    expect(await screen.findByRole("status")).toHaveTextContent(en.portal.auth.forgotSent);
  });

  it("spends the token from the link", async () => {
    render(<PortalResetPasswordScreen token="abc" email="hana@example.test" />);

    fireEvent.change(screen.getByLabelText(en.portal.auth.newPassword), {
      target: { value: "a-brand-new-passphrase" },
    });
    fireEvent.change(screen.getByLabelText(en.portal.auth.passwordConfirm), {
      target: { value: "a-brand-new-passphrase" },
    });

    await userEvent.click(screen.getByRole("button", { name: en.portal.auth.reset }));

    await waitFor(() => expect(posts).toHaveLength(1));
    expect(posts[0]!.body.token).toBe("abc");
  });

  it("says a spent or expired link is gone, without saying which", async () => {
    status = 422;
    render(<PortalResetPasswordScreen token="abc" email="hana@example.test" />);

    await userEvent.click(screen.getByRole("button", { name: en.portal.auth.reset }));

    /*
     * One message for expired, already-used and forged. Telling them apart
     * would confirm to somebody holding a stale link that it was once real.
     */
    expect(await screen.findByRole("alert")).toHaveTextContent(en.portal.auth.resetExpired);
  });

  it("sends the person to sign in once it worked", async () => {
    render(<PortalResetPasswordScreen token="abc" email="hana@example.test" />);

    await userEvent.click(screen.getByRole("button", { name: en.portal.auth.reset }));

    expect(await screen.findByRole("status")).toHaveTextContent(en.portal.auth.resetDone);
    expect(screen.getByRole("link", { name: en.portal.auth.signIn })).toBeInTheDocument();
  });
});
