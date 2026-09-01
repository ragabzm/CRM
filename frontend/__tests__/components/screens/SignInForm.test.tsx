import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const replace = vi.fn();
const params = { value: new URLSearchParams() };

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace, push: vi.fn(), refresh: vi.fn() }),
  usePathname: () => "/sign-in",
  useSearchParams: () => params.value,
}));

const login = vi.fn();
vi.mock("@/lib/auth/api", async () => {
  const actual = await vi.importActual<typeof import("@/lib/auth/api")>("@/lib/auth/api");

  return { ...actual, login: (...args: unknown[]) => login(...args) };
});

import { SignInForm } from "@/components/screens/auth/SignInForm";
import { AuthError } from "@/lib/auth/api";
import { en, render, screen } from "@/__tests__/helpers/intl";

const USER = { id: 1, name: "Hana", email: "h@ragab.test", preferred_locale: "en", roles: [] };

function problem(code: string, status: number) {
  return new AuthError("failed", {
    type: `https://errors.ragab-crm/${code}`,
    title: "t",
    status,
    instance: "/api/v1/auth/login",
    code,
    trace_id: "01HZY",
  }, status);
}

beforeEach(() => {
  replace.mockClear();
  login.mockReset();
  params.value = new URLSearchParams();
});

afterEach(() => {
  vi.clearAllMocks();
});

describe("the sign-in form", () => {
  it("renders labelled email and password fields", () => {
    render(<SignInForm />);

    expect(screen.getByLabelText(en.auth.signIn.email)).toBeInTheDocument();
    expect(screen.getByLabelText(en.auth.signIn.password)).toBeInTheDocument();
  });

  it("uses a password input, so the value is never shown", () => {
    render(<SignInForm />);

    expect(screen.getByLabelText(en.auth.signIn.password)).toHaveAttribute("type", "password");
  });

  it("declares autocomplete so a password manager can fill it", () => {
    render(<SignInForm />);

    expect(screen.getByLabelText(en.auth.signIn.email)).toHaveAttribute("autocomplete", "username");
    expect(screen.getByLabelText(en.auth.signIn.password)).toHaveAttribute(
      "autocomplete",
      "current-password",
    );
  });

  it("signs in and lands on the home destination", async () => {
    login.mockResolvedValue(USER);
    const user = userEvent.setup();
    render(<SignInForm />);

    await user.type(screen.getByLabelText(en.auth.signIn.email), "h@ragab.test");
    await user.type(screen.getByLabelText(en.auth.signIn.password), "Correct-Horse-9");
    await user.click(screen.getByRole("button", { name: en.auth.signIn.submit }));

    expect(login).toHaveBeenCalledWith({ email: "h@ragab.test", password: "Correct-Horse-9" });
    expect(replace).toHaveBeenCalledWith("/");
  });

  it("returns to where the session expired", async () => {
    params.value = new URLSearchParams("redirect=%2Ftickets%2F000123");
    login.mockResolvedValue(USER);
    const user = userEvent.setup();
    render(<SignInForm />);

    await user.type(screen.getByLabelText(en.auth.signIn.email), "h@ragab.test");
    await user.type(screen.getByLabelText(en.auth.signIn.password), "Correct-Horse-9");
    await user.click(screen.getByRole("button", { name: en.auth.signIn.submit }));

    expect(replace).toHaveBeenCalledWith("/tickets/000123");
  });

  it.each([
    ["https://evil.example", "an absolute URL"],
    ["//evil.example", "a protocol-relative URL"],
    ["/\\\\evil.example", "a backslash-prefixed URL"],
  ])("refuses %s as a redirect target (%s)", async (target) => {
    params.value = new URLSearchParams();
    params.value.set("redirect", target);
    login.mockResolvedValue(USER);
    const user = userEvent.setup();
    render(<SignInForm />);

    await user.type(screen.getByLabelText(en.auth.signIn.email), "h@ragab.test");
    await user.type(screen.getByLabelText(en.auth.signIn.password), "Correct-Horse-9");
    await user.click(screen.getByRole("button", { name: en.auth.signIn.submit }));

    // ?redirect= is attacker-controllable; on a sign-in page an open redirect
    // is a phishing primitive.
    expect(replace).toHaveBeenCalledWith("/");
  });

  it("shows the invalid-credentials message without echoing the attempt", async () => {
    login.mockRejectedValue(problem("security.invalid_credentials", 401));
    const user = userEvent.setup();
    render(<SignInForm />);

    await user.type(screen.getByLabelText(en.auth.signIn.email), "h@ragab.test");
    await user.type(screen.getByLabelText(en.auth.signIn.password), "the-guess");
    await user.click(screen.getByRole("button", { name: en.auth.signIn.submit }));

    const alert = await screen.findByRole("alert");
    expect(alert).toHaveTextContent(en.auth.signIn.errorInvalid);
    expect(alert.textContent).not.toContain("the-guess");
    expect(replace).not.toHaveBeenCalled();
  });

  it("distinguishes a rate limit from a bad password", async () => {
    login.mockRejectedValue(problem("platform.too_many_requests", 429));
    const user = userEvent.setup();
    render(<SignInForm />);

    await user.type(screen.getByLabelText(en.auth.signIn.email), "h@ragab.test");
    await user.type(screen.getByLabelText(en.auth.signIn.password), "x");
    await user.click(screen.getByRole("button", { name: en.auth.signIn.submit }));

    expect(await screen.findByRole("alert")).toHaveTextContent(en.auth.signIn.errorLocked);
  });

  it("collapses an unexpected failure to a generic message", async () => {
    login.mockRejectedValue(new Error("Undefined index: internal_thing"));
    const user = userEvent.setup();
    render(<SignInForm />);

    await user.type(screen.getByLabelText(en.auth.signIn.email), "h@ragab.test");
    await user.type(screen.getByLabelText(en.auth.signIn.password), "x");
    await user.click(screen.getByRole("button", { name: en.auth.signIn.submit }));

    const alert = await screen.findByRole("alert");
    expect(alert).toHaveTextContent(en.auth.signIn.errorGeneric);
    // An error body is the easiest place to leak an internal detail.
    expect(alert.textContent).not.toContain("internal_thing");
  });

  it("offers a route to password recovery", () => {
    render(<SignInForm />);

    expect(screen.getByRole("link", { name: en.auth.signIn.forgot })).toHaveAttribute(
      "href",
      "/forgot-password",
    );
  });
});
