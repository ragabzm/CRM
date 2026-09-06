"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { createBranch, setBranchActive, type Branch } from "@/lib/api/admin";
import { ApiError } from "@/lib/api/errors";
import { cn, TOUCH_TARGET } from "@/lib/utils";

export interface BranchListProps {
  branches: Branch[];
  onChanged: () => void;
}

/**
 * The offices, as labels.
 *
 * THERE IS NO DELETE, and that is not an omission to fill in later. A branch
 * that closed still describes where three years of tickets happened; removing
 * the row would either take those tickets with it or leave them pointing at
 * nothing. Deactivating stops it being offered and leaves every record that
 * already carries it intact.
 *
 * A short list — a business has offices, not thousands of them — so a plain
 * table with inline actions rather than a paginated searchable surface.
 */
export function BranchList({ branches, onChanged }: BranchListProps) {
  const t = useTranslations("admin.organisation.branches");

  const [name, setName] = useState("");
  const [code, setCode] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function run(action: () => Promise<unknown>) {
    setBusy(true);
    setError(null);

    try {
      await action();
      onChanged();
    } catch (caught) {
      /*
       * The server's own words. "That code is already taken" is something an
       * administrator can act on; "could not save" is not.
       */
      setError(caught instanceof ApiError ? (caught.problem?.detail ?? t("failed")) : t("failed"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex flex-col gap-3" data-slot="branch-list">
      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      {branches.length === 0 ? (
        // Not an error and not a gap: a business with one office has no
        // branches, and every record carrying none is the correct state.
        <p className="text-sm text-fg-muted">{t("none")}</p>
      ) : (
        <ul className="flex flex-col divide-y divide-border-subtle">
          {branches.map((branch) => (
            <li
              key={branch.id}
              className="flex flex-wrap items-center gap-3 py-2"
              data-slot="branch-row"
              data-active={branch.is_active}
            >
              <span className="flex-1 text-sm text-fg-default" dir="auto">
                {branch.name}
              </span>

              <span className="num text-xs tabular-nums text-fg-muted">{branch.code}</span>

              <span className="text-xs text-fg-muted">
                {branch.is_active ? t("active") : t("inactive")}
              </span>

              <button
                type="button"
                disabled={busy}
                onClick={() => void run(() => setBranchActive(branch.id, !branch.is_active))}
                className={cn("text-xs underline text-fg-default", TOUCH_TARGET)}
                data-slot="branch-toggle"
              >
                {branch.is_active ? t("deactivate") : t("reactivate")}
              </button>
            </li>
          ))}
        </ul>
      )}

      {/*
        Labelled "Branch name", not "Name".
        
        The departments panel sits on the same page with a field labelled
        "Name", and two identically-labelled inputs are ambiguous to anybody
        navigating by label — which is both a screen-reader problem and the
        reason a test could no longer find either one.
      */}
      <form
        className="flex flex-wrap items-end gap-2"
        onSubmit={(event) => {
          event.preventDefault();
          void run(async () => {
            await createBranch({ name, code });
            setName("");
            setCode("");
          });
        }}
      >
        <FormField
          label={t("name")}
          value={name}
          dir="auto"
          maxLength={120}
          onChange={(event) => setName(event.target.value)}
        />

        <FormField
          label={t("code")}
          hint={t("codeHint")}
          value={code}
          maxLength={16}
          className="uppercase"
          onChange={(event) => setCode(event.target.value)}
        />

        <SubmitButton pending={busy} disabled={name.trim() === "" || code.trim() === ""}>
          {t("add")}
        </SubmitButton>
      </form>
    </div>
  );
}
