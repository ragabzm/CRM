"use client";

import { useTranslations } from "next-intl";

import { FormField } from "@/components/domain/FormField/FormField";
import { Button } from "@/components/ui/button";
import type { AuditFilters } from "@/lib/api/admin";

export interface AuditFilterBarProps {
  filters: AuditFilters;
  onChange: (filters: AuditFilters) => void;
  /** The action vocabulary the server publishes alongside the data. */
  actions: string[];
  labelForAction: (action: string) => string;
}

/**
 * Three filters: who, what, and when.
 *
 * Three, deliberately. An audit log invites making everything filterable, which
 * produces a query nobody can index and a bar nobody can read. These answer the
 * questions actually asked after an incident; anything else is served by
 * narrowing these and reading.
 *
 * A native select rather than the Radix one: this bar renders inside a form
 * whose values are read on submit, and a native control needs no controlled
 * state to do that.
 */
export function AuditFilterBar({
  filters,
  onChange,
  actions,
  labelForAction,
}: AuditFilterBarProps) {
  const t = useTranslations("audit.filters");

  function update(patch: Partial<AuditFilters>) {
    // Any filter change returns to page 1. Staying on page 4 of a narrower
    // result set shows an empty screen that looks like "no matches".
    onChange({ ...filters, ...patch, page: 1 });
  }

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-wrap items-end gap-3" data-slot="audit-filters">
        <FormField
          label={t("actor")}
          placeholder={t("actorPlaceholder")}
          value={filters.actor_search ?? ""}
          onChange={(event) => update({ actor_search: event.target.value })}
        />

        <label className="flex flex-col gap-1 text-sm font-medium text-fg-default">
          {t("action")}
          <select
            value={filters.action ?? ""}
            onChange={(event) => update({ action: event.target.value })}
            className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
          >
            <option value="">{t("anyAction")}</option>
            {actions.map((action) => (
              <option key={action} value={action}>
                {labelForAction(action)}
              </option>
            ))}
          </select>
        </label>

        <FormField
          label={t("from")}
          type="date"
          value={filters.from ?? ""}
          onChange={(event) => update({ from: event.target.value })}
        />

        <FormField
          label={t("to")}
          type="date"
          value={filters.to ?? ""}
          onChange={(event) => update({ to: event.target.value })}
        />

        <Button variant="secondary" onClick={() => onChange({ page: 1 })}>
          {t("clear")}
        </Button>
      </div>

      {/* Named because "entries on the 1st" means different rows depending on
          the timezone, and the reader cannot see ours. */}
      <p className="text-xs text-fg-muted">{t("dateNote")}</p>
    </div>
  );
}
