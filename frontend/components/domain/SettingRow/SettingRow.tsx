"use client";

import { useTranslations } from "next-intl";
import { useId, useState } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import type { Setting } from "@/lib/api/admin";
import { cn } from "@/lib/utils";

export interface SettingRowProps {
  setting: Setting;
  /** Human label. Falls back to the server's summary when the UI has no copy. */
  label?: string;
  /** Saves the value. Rejects with an ApiError whose problem carries the reason. */
  onSave: (value: unknown) => Promise<void>;
  className?: string;
}

/** The editable text form of a value, by type. */
function toDraft(setting: Setting): string {
  const { value, type } = setting;

  // A secret's value never arrives — only the mask — so the box starts empty
  // and an empty box means "leave it alone", not "set it to nothing".
  if (setting.secret) return "";

  if (value === null || value === undefined) return "";
  if (type === "json") return JSON.stringify(value, null, 2);

  return String(value);
}

/** Parses the draft back into the type the server will accept. */
function fromDraft(setting: Setting, draft: string): { ok: true; value: unknown } | { ok: false } {
  switch (setting.type) {
    case "int":
    case "duration_seconds": {
      // Not parseInt: "12abc" would silently become 12.
      if (!/^-?\d+$/.test(draft.trim())) return { ok: false };
      return { ok: true, value: Number(draft.trim()) };
    }
    case "json": {
      try {
        return { ok: true, value: JSON.parse(draft) };
      } catch {
        return { ok: false };
      }
    }
    default:
      return { ok: true, value: draft };
  }
}

/**
 * One setting, rendered according to its declared type.
 *
 * The type comes from the server's registry rather than from a switch the
 * frontend maintains separately, so a new setting appears in the console with
 * the right control the moment a module declares it — and cannot be edited with
 * a control that does not match the rule it will be judged by.
 */
export function SettingRow({ setting, label, onSave, className }: SettingRowProps) {
  const t = useTranslations("admin.setting");
  const id = useId();

  const [draft, setDraft] = useState(() => toDraft(setting));
  const [state, setState] = useState<"idle" | "saving" | "saved">("idle");
  const [error, setError] = useState<string | null>(null);

  /*
   * Re-sync with the server when — and only when — the stored value actually
   * changed. Keyed on the value rather than on object identity so an unrelated
   * re-fetch cannot wipe out what someone is halfway through typing, and
   * adjusted during render rather than in an effect so there is never a frame
   * showing the old value.
   */
  const serverValue = JSON.stringify(setting.value ?? null);
  const [syncedTo, setSyncedTo] = useState(serverValue);

  if (syncedTo !== serverValue) {
    setSyncedTo(serverValue);
    setDraft(toDraft(setting));
  }

  async function save(value: unknown) {
    setError(null);
    setState("saving");

    try {
      await onSave(value);
      setState("saved");
    } catch (caught) {
      setState("idle");
      // The server's message names the rule that was broken; ours would only
      // be able to say "invalid".
      const detail =
        caught && typeof caught === "object" && "problem" in caught
          ? ((caught as { problem: { detail?: string } | null }).problem?.detail ?? null)
          : null;
      setError(detail ?? t("genericError"));
    }
  }

  const describedBy = `${id}-summary`;

  if (setting.type === "bool") {
    const checked = setting.value === true;

    return (
      <div className={cn("flex flex-col gap-1", className)} data-setting={setting.key}>
        <label className="flex items-center gap-3 text-sm font-medium text-fg-default">
          <Checkbox
            checked={checked}
            aria-describedby={describedBy}
            disabled={state === "saving"}
            onCheckedChange={(next) => void save(next === true)}
          />
          <span>{label ?? setting.key}</span>
        </label>
        <p id={describedBy} className="text-xs text-fg-muted">
          {setting.summary}
        </p>
        {error && <FormAlert tone="error">{error}</FormAlert>}
      </div>
    );
  }

  if (setting.type === "enum" && setting.allowed_values) {
    return (
      <div className={cn("flex flex-col gap-1", className)} data-setting={setting.key}>
        <label htmlFor={id} className="text-sm font-medium text-fg-default">
          {label ?? setting.key}
        </label>
        <Select
          {...(typeof setting.value === "string" ? { value: setting.value } : {})}
          disabled={state === "saving"}
          onValueChange={(next) => void save(next)}
        >
          <SelectTrigger id={id} aria-describedby={describedBy} className="w-full max-w-sm">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {setting.allowed_values.map((option) => (
              <SelectItem key={option} value={option}>
                {option}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <p id={describedBy} className="text-xs text-fg-muted">
          {setting.summary}
        </p>
        {error && <FormAlert tone="error">{error}</FormAlert>}
      </div>
    );
  }

  const multiline = setting.type === "json";

  return (
    <form
      className={cn("flex flex-col gap-1", className)}
      data-setting={setting.key}
      onSubmit={(event) => {
        event.preventDefault();

        // A secret with an untouched (empty) box is not a request to blank it.
        if (setting.secret && draft === "") return;

        const parsed = fromDraft(setting, draft);
        if (!parsed.ok) {
          setError(t("genericError"));
          return;
        }

        void save(parsed.value);
      }}
    >
      <label htmlFor={id} className="text-sm font-medium text-fg-default">
        {label ?? setting.key}
      </label>

      {multiline ? (
        <textarea
          id={id}
          value={draft}
          aria-describedby={describedBy}
          rows={6}
          onChange={(event) => setDraft(event.target.value)}
          className="num rounded-md border border-border-default bg-surface-base px-3 py-2 font-mono text-xs text-fg-default"
        />
      ) : (
        <Input
          id={id}
          value={draft}
          aria-describedby={describedBy}
          type={setting.secret ? "password" : "text"}
          inputMode={
            setting.type === "int" || setting.type === "duration_seconds" ? "numeric" : undefined
          }
          placeholder={setting.secret ? t("secretPlaceholder") : undefined}
          onChange={(event) => setDraft(event.target.value)}
          className="max-w-sm"
        />
      )}

      <p id={describedBy} className="text-xs text-fg-muted">
        {setting.summary}
        {setting.secret && (
          /*
           * Distinguishes "a password is saved" from "no password is set".
           * Without this the console shows an empty box in both cases.
           *
           * Read from `configured`, NOT from `value`. The server used to send a
           * row of dots for a set secret and null for an unset one, so `value`
           * carried the answer; it now sends null either way, because a mask is
           * a value-shaped thing that invites a reveal control and pastes into
           * config files as literal bullets. Reading `value` here would have
           * quietly reported every credential as unset.
           */
          <> {setting.configured ? t("secretSet") : t("secretUnset")}</>
        )}
      </p>

      <div className="flex items-center gap-3 pt-1">
        <Button type="submit" size="sm" disabled={state === "saving"}>
          {state === "saving" ? t("saving") : t("save")}
        </Button>
        {state === "saved" && (
          <span role="status" className="text-xs text-state-success">
            {t("saved")}
          </span>
        )}
      </div>

      {error && <FormAlert tone="error">{error}</FormAlert>}
    </form>
  );
}
