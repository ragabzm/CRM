import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/profile",
}));

import { ProfileScreen } from "@/components/screens/profile/ProfileScreen";

/**
 * A form that cannot show what it is editing has to say so.
 *
 * The load failure was swallowed with `.catch(() => undefined)`, so the screen
 * offered an ordinary-looking form with empty Name and Email and no message —
 * indistinguishable from a profile that genuinely has no name. Typing into it
 * and pressing Save would have written that emptiness over the real record.
 */

const USER = {
  id: 1,
  name: "Dana Faris",
  email: "dana@example.test",
  preferred_locale: "en",
  roles: ["agent"],
};

let status = 200;

beforeEach(() => {
  status = 200;

  vi.stubGlobal(
    "fetch",
    vi.fn(
      async () =>
        new Response(JSON.stringify(status === 200 ? USER : { status, code: "x" }), {
          status,
          headers: { "Content-Type": "application/json" },
        }),
    ),
  );
});

afterEach(() => vi.unstubAllGlobals());

describe("the profile screen when loading fails", () => {
  it("says so instead of showing a blank form", async () => {
    status = 500;
    render(<ProfileScreen />);

    expect(await screen.findByRole("alert")).toHaveTextContent("Your profile could not be loaded.");
  });

  it("will not let the reader overwrite a record it never showed them", async () => {
    status = 500;
    render(<ProfileScreen />);

    await screen.findByRole("alert");

    expect(screen.getByLabelText("Name")).toBeDisabled();
    expect(screen.getByRole("button", { name: "Save changes" })).toBeDisabled();
  });

  it("offers a retry that recovers the form", async () => {
    status = 500;
    render(<ProfileScreen />);

    const alert = await screen.findByRole("alert");
    status = 200;

    await userEvent.click(within(alert).getByRole("button", { name: "Retry" }));

    await waitFor(() => expect(screen.getByLabelText("Name")).toHaveValue("Dana Faris"));
    expect(screen.queryByRole("alert")).toBeNull();
  });
});

describe("the profile screen while loading", () => {
  it("says it is working rather than showing an empty form silently", async () => {
    render(<ProfileScreen />);

    expect(await screen.findByRole("status")).toHaveTextContent("Loading your profile…");
  });

  it("stops saying so once the record arrives", async () => {
    render(<ProfileScreen />);

    await waitFor(() => expect(screen.getByLabelText("Name")).toHaveValue("Dana Faris"));
    expect(screen.queryByText("Loading your profile…")).toBeNull();
  });
});

describe("the profile screen when loading succeeds", () => {
  it("fills the form and enables it", async () => {
    render(<ProfileScreen />);

    await waitFor(() => expect(screen.getByLabelText("Name")).toHaveValue("Dana Faris"));

    expect(screen.getByLabelText("Name")).toBeEnabled();
    expect(screen.queryByRole("alert")).toBeNull();
  });
});
