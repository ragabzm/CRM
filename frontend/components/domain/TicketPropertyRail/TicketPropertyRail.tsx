"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { ApiError } from "@/lib/api/errors";
import { updateTicketProperties, type Ticket } from "@/lib/api/tickets";

export interface TicketPropertyRailProps {
  ticket: Ticket;
  categories: Array<{ id: number; name: string }>;
  assignees: Array<{ id: number; name: string }>;
  departments: Array<{ id: number; name: string }>;
  /** False when the reader may see the ticket but not change it. */
  editable: boolean;
  onChanged: (ticket: Ticket) => void;
  /** Refetches everything after a conflict, without touching the composer. */
  onReload: () => void;
}

const STATUSES = ["open", "pending", "resolved", "closed"] as const;
const PRIORITIES = ["low", "normal", "high", "urgent"] as const;

/**
 * The five properties two people genuinely contend over.
 *
 * Every change here carries the version the screen was loaded with, as
 * `If-Match`. If someone else moved first the server refuses with 409 rather
 * than letting this write silently revert theirs — which is the failure nobody
 * notices until a customer asks why their ticket was reopened.
 *
 * The refusal never touches the composer. An agent three sentences into a reply
 * has not done anything wrong, and taking their words away to report someone
 * else's edit would punish them for it.
 */
export function TicketPropertyRail({
  ticket,
  categories,
  assignees,
  departments,
  editable,
  onChanged,
  onReload,
}: TicketPropertyRailProps) {
  const t = useTranslations("ticket.propertyRail");
  const conflictCopy = useTranslations("ticket.conflict");

  const [saving, setSaving] = useState(false);
  const [conflict, setConflict] = useState(false);
  const [failed, setFailed] = useState(false);

  async function change(field: string, value: string | number | null) {
    setSaving(true);
    setConflict(false);
    setFailed(false);

    try {
      const updated = await updateTicketProperties(ticket.id, ticket.version, {
        [field]: value,
      });

      onChanged(updated);
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 409) {
        setConflict(true);
      } else {
        setFailed(true);
      }
    } finally {
      setSaving(false);
    }
  }

  function pick(field: string) {
    return (raw: string) => {
      // "None" is a real choice — unassigning is not the absence of one.
      const value = raw === "" ? null : /^\d+$/.test(raw) ? Number(raw) : raw;

      void change(field, value);
    };
  }

  return (
    <section
      data-slot="ticket-property-rail"
      aria-label={t("title")}
      className="flex flex-col gap-4"
    >
      <h2 className="text-base font-semibold text-fg-default">{t("title")}</h2>

      {!editable && (
        <p className="text-xs text-fg-muted" data-slot="rail-read-only">
          {t("readOnly")}
        </p>
      )}

      {conflict && (
        <FormAlert tone="error" action={{ label: conflictCopy("reload"), onSelect: onReload }}>
          {`${conflictCopy("title")} ${conflictCopy("body")}`}
        </FormAlert>
      )}

      {failed && <FormAlert tone="error">{t("error")}</FormAlert>}

      <RailSelect
        label={t("status")}
        value={ticket.status}
        disabled={!editable || saving}
        options={STATUSES.map((value) => ({ value, label: value }))}
        onChange={pick("status")}
      />

      <RailSelect
        label={t("priority")}
        value={ticket.priority}
        disabled={!editable || saving}
        options={PRIORITIES.map((value) => ({ value, label: value }))}
        onChange={pick("priority")}
      />

      <RailSelect
        label={t("category")}
        value={ticket.category_id === null ? "" : String(ticket.category_id)}
        disabled={!editable || saving}
        options={[
          { value: "", label: t("none") },
          ...categories.map((c) => ({ value: String(c.id), label: c.name })),
        ]}
        onChange={pick("category_id")}
      />

      <RailSelect
        label={t("assignee")}
        value={ticket.assignee_id === null ? "" : String(ticket.assignee_id)}
        disabled={!editable || saving}
        options={[
          { value: "", label: t("unassigned") },
          ...assignees.map((a) => ({ value: String(a.id), label: a.name })),
        ]}
        onChange={pick("assignee_id")}
      />

      <RailSelect
        label={t("department")}
        value={ticket.department_id === null ? "" : String(ticket.department_id)}
        disabled={!editable || saving}
        options={[
          { value: "", label: t("none") },
          ...departments.map((d) => ({ value: String(d.id), label: d.name })),
        ]}
        onChange={pick("department_id")}
      />

      {/*
        Read-only, and said so. The service level is derived from the policy
        that applies to this ticket; an agent who could type it could promise
        the customer something the business never agreed to.
      */}
      <FormField
        label={t("sla")}
        name="sla"
        value={t("slaReadOnly")}
        readOnly
        disabled
        hint={t("slaReadOnly")}
      />
    </section>
  );
}

function RailSelect({
  label,
  value,
  options,
  disabled,
  onChange,
}: {
  label: string;
  value: string;
  options: Array<{ value: string; label: string }>;
  disabled: boolean;
  onChange: (value: string) => void;
}) {
  return (
    <label className="flex flex-col gap-1 text-sm">
      <span className="font-medium text-fg-default">{label}</span>

      <Select value={value} onValueChange={onChange} disabled={disabled}>
        <SelectTrigger aria-label={label}>
          <SelectValue />
        </SelectTrigger>

        <SelectContent>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </label>
  );
}
