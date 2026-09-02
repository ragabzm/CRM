import { fireEvent, render, screen, waitFor, withIntl } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { SettingRow } from "@/components/domain/SettingRow/SettingRow";
import type { Setting } from "@/lib/api/admin";

function setting(overrides: Partial<Setting> = {}): Setting {
  return {
    key: "tickets.auto_close_hours",
    type: "int",
    value: 168,
    default: 168,
    secret: false,
    summary: "Hours a resolved ticket waits before closing itself.",
    allowed_values: null,
    ...overrides,
  };
}

/** An ApiError-shaped rejection, as the transport produces. */
function refusal(detail: string) {
  return Object.assign(new Error(detail), {
    problem: { detail, code: "platform.setting_invalid", status: 422 },
  });
}

describe("SettingRow renders a setting by its declared type", () => {
  it("sends the integer as a number, not as a string", async () => {
    const onSave = vi.fn().mockResolvedValue(undefined);
    render(<SettingRow setting={setting()} label="Auto close" onSave={onSave} />);

    const input = screen.getByLabelText("Auto close");
    await userEvent.clear(input);
    await userEvent.type(input, "24");
    await userEvent.click(screen.getByRole("button", { name: "Save" }));

    // A string here would be refused by the type check on the server.
    await waitFor(() => expect(onSave).toHaveBeenCalledWith(24));
  });

  it("refuses a non-numeric draft before it reaches the server", async () => {
    const onSave = vi.fn().mockResolvedValue(undefined);
    render(<SettingRow setting={setting()} label="Auto close" onSave={onSave} />);

    const input = screen.getByLabelText("Auto close");
    await userEvent.clear(input);
    await userEvent.type(input, "24abc");
    await userEvent.click(screen.getByRole("button", { name: "Save" }));

    // Not parseInt: "24abc" must not silently become 24.
    expect(onSave).not.toHaveBeenCalled();
    expect(await screen.findByRole("alert")).toBeInTheDocument();
  });

  it("shows the server's reason, not a generic message", async () => {
    const onSave = vi
      .fn()
      .mockRejectedValue(refusal("Auto-close must be between 1 hour and 90 days."));

    render(<SettingRow setting={setting()} label="Auto close" onSave={onSave} />);
    await userEvent.click(screen.getByRole("button", { name: "Save" }));

    // The administrator is told the bound they broke, which the frontend does
    // not know and must not invent.
    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Auto-close must be between 1 hour and 90 days.",
    );
  });

  it("saves a boolean on toggle without a separate save press", async () => {
    const onSave = vi.fn().mockResolvedValue(undefined);
    render(
      <SettingRow
        setting={setting({ key: "x.flag", type: "bool", value: false, default: false })}
        label="Enabled"
        onSave={onSave}
      />,
    );

    await userEvent.click(screen.getByRole("checkbox"));

    await waitFor(() => expect(onSave).toHaveBeenCalledWith(true));
  });

  it("renders an enum as a listbox limited to its allowed values", () => {
    render(
      <SettingRow
        setting={setting({
          key: "platform.default_locale",
          type: "enum",
          value: "en",
          default: "en",
          allowed_values: ["en", "ar"],
        })}
        label="Default language"
        onSave={vi.fn()}
      />,
    );

    // A select, not a free-text box that can be filled with a locale the
    // application does not have.
    expect(screen.getByRole("combobox", { name: "Default language" })).toBeInTheDocument();
    expect(screen.queryByRole("textbox")).not.toBeInTheDocument();
  });

  it("never puts a secret's value in the DOM", () => {
    render(
      <SettingRow
        setting={setting({
          key: "email.mailbox.password",
          type: "string",
          value: "••••••••",
          default: null,
          secret: true,
        })}
        label="Password"
        onSave={vi.fn()}
      />,
    );

    const input = screen.getByLabelText("Password");
    // Starts empty: the real value never arrives, only the mask.
    expect(input).toHaveValue("");
    expect(input).toHaveAttribute("type", "password");
  });

  it("distinguishes a saved secret from an unset one", () => {
    const { rerender } = render(
      <SettingRow
        setting={setting({ key: "s", type: "string", value: null, default: null, secret: true })}
        label="Password"
        onSave={vi.fn()}
      />,
    );

    expect(screen.getByText(/Not set\./)).toBeInTheDocument();

    rerender(
      withIntl(
        <SettingRow
          setting={setting({
            key: "s",
            type: "string",
            value: "••••••••",
            default: null,
            secret: true,
          })}
          label="Password"
          onSave={vi.fn()}
        />,
      ),
    );

    // Otherwise both cases show an empty box and the administrator cannot tell
    // whether the mailbox is configured.
    expect(screen.getByText(/A value is saved/)).toBeInTheDocument();
  });

  it("does not blank a secret when the untouched box is submitted", async () => {
    const onSave = vi.fn().mockResolvedValue(undefined);
    render(
      <SettingRow
        setting={setting({
          key: "s",
          type: "string",
          value: "••••••••",
          default: null,
          secret: true,
        })}
        label="Password"
        onSave={onSave}
      />,
    );

    await userEvent.click(screen.getByRole("button", { name: "Save" }));

    // An empty box means "leave it alone", not "set it to nothing".
    expect(onSave).not.toHaveBeenCalled();
  });

  it("parses a json setting into a value, not a string", async () => {
    const onSave = vi.fn().mockResolvedValue(undefined);
    render(
      <SettingRow
        setting={setting({ key: "sla.holidays", type: "json", value: [], default: [] })}
        label="Holidays"
        onSave={onSave}
      />,
    );

    const box = screen.getByLabelText("Holidays");
    fireEvent.change(box, { target: { value: '["2026-01-01"]' } });
    await userEvent.click(screen.getByRole("button", { name: "Save" }));

    await waitFor(() => expect(onSave).toHaveBeenCalledWith(["2026-01-01"]));
  });

  it("refuses malformed json before sending it", async () => {
    const onSave = vi.fn().mockResolvedValue(undefined);
    render(
      <SettingRow
        setting={setting({ key: "sla.holidays", type: "json", value: [], default: [] })}
        label="Holidays"
        onSave={onSave}
      />,
    );

    const box = screen.getByLabelText("Holidays");
    fireEvent.change(box, { target: { value: "{not json" } });
    await userEvent.click(screen.getByRole("button", { name: "Save" }));

    expect(onSave).not.toHaveBeenCalled();
  });

  it("adopts a new server value without an effect round trip", () => {
    const { rerender } = render(
      <SettingRow setting={setting()} label="Auto close" onSave={vi.fn()} />,
    );

    expect(screen.getByLabelText("Auto close")).toHaveValue("168");

    rerender(
      withIntl(<SettingRow setting={setting({ value: 24 })} label="Auto close" onSave={vi.fn()} />),
    );

    // Adjusted during render, so there is never a frame showing the old value.
    expect(screen.getByLabelText("Auto close")).toHaveValue("24");
  });

  it("keeps an in-progress draft when an unrelated re-fetch returns the same value", async () => {
    const { rerender } = render(
      <SettingRow setting={setting()} label="Auto close" onSave={vi.fn()} />,
    );

    const input = screen.getByLabelText("Auto close");
    await userEvent.clear(input);
    await userEvent.type(input, "48");

    // A fresh object from the server carrying the SAME value must not wipe out
    // what someone is halfway through typing.
    rerender(withIntl(<SettingRow setting={setting()} label="Auto close" onSave={vi.fn()} />));

    expect(screen.getByLabelText("Auto close")).toHaveValue("48");
  });

  it("describes the control with the setting's own summary", () => {
    render(<SettingRow setting={setting()} label="Auto close" onSave={vi.fn()} />);

    expect(screen.getByLabelText("Auto close")).toHaveAccessibleDescription(
      /Hours a resolved ticket waits/,
    );
  });
});
