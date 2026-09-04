"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { SlaIndicator } from "@/components/domain/SlaIndicator/SlaIndicator";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { ApiError } from "@/lib/api/errors";
import { updateTicketProperties, type Ticket } from "@/lib/api/tickets";
import { AvatarChip } from "@/components/domain/AvatarChip/AvatarChip";
import { useFormat } from "@/lib/format/useFormat";
import { cn } from "@/lib/utils";

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
  const tStatus = useTranslations("tickets.status");
  const tPriority = useTranslations("tickets.priority");
  const tChannel = useTranslations("tickets.channel");
  const format = useFormat();

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

      {/*
        FOUR BANDS, not a list of six labelled selects.
        Grouping by WHAT KIND of thing a property is — what is happening right
        now, who holds it, how it is filed, where it came from — is what turns
        the rail from a form into something readable at a glance. The rail is
        the workspace's signature in the design, and it had become the one
        thing every screen already has: a column of dropdowns.
      */}
      <Band title={t("bands.state")} note={t("bands.stateNote")}>
        <div className="flex items-stretch gap-0">
          <div className="flex-none basis-28">
            <RailSelect
              label={t("status")}
              value={ticket.status}
              disabled={!editable || saving}
              options={STATUSES.map((value) => ({ value, label: tStatus(value) }))}
              onChange={pick("status")}
            />
          </div>

          {/*
            The only vertical hairline in the product. It marks this band as
            the live one — everything else is separated by horizontal rules.
          */}
          <div aria-hidden="true" className="mx-3 w-px flex-none self-stretch bg-border-subtle" />

          <div className="min-w-0 flex-1">
            <RailFact label={t("sla")} note={t("slaReadOnly")}>
              <SlaIndicator sla={ticket.sla ?? null} variant="full" />
            </RailFact>
          </div>
        </div>

        <RailSelect
          label={t("priority")}
          value={ticket.priority}
          disabled={!editable || saving}
          options={PRIORITIES.map((value) => ({ value, label: tPriority(value) }))}
          onChange={pick("priority")}
        />
      </Band>

      <Band title={t("bands.assignment")}>
        <RailSelect
          label={t("assignee")}
          value={ticket.assignee_id === null ? "" : String(ticket.assignee_id)}
          disabled={!editable || saving}
          options={[
            { value: "", label: t("unassigned") },
            ...assignees.map((a) => ({ value: String(a.id), label: a.name })),
          ]}
          onChange={pick("assignee_id")}
        >
          {/*
            The FACE only. The select above already prints the name, so
            rendering it again underneath put the same fact twice in fifty
            pixels. The circle adds what the dropdown cannot — who this is at
            a glance, and a shape that says "nobody" when it is nobody.
          */}
          <AvatarChip
            name={assignees.find((a) => a.id === ticket.assignee_id)?.name ?? null}
            unassignedLabel={t("unassigned")}
          />
        </RailSelect>
      </Band>

      <Band title={t("bands.classification")}>
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
          label={t("department")}
          value={ticket.department_id === null ? "" : String(ticket.department_id)}
          disabled={!editable || saving}
          options={[
            { value: "", label: t("none") },
            ...departments.map((d) => ({ value: String(d.id), label: d.name })),
          ]}
          onChange={pick("department_id")}
        />
      </Band>

      <Band title={t("bands.origin")}>
        {/*
          Where the edge stops. Everything above can be changed; how a request
          reached the desk cannot, and the dashed rule says so without a
          sentence.
        */}
        <RailFact label={t("channel")}>
          <span>{tChannel(ticket.channel)}</span>
        </RailFact>

        <RailFact label={t("openedAt")}>
          <time dateTime={ticket.created_at ?? undefined}>
            {ticket.created_at === null ? "—" : format.dateTime(ticket.created_at)}
          </time>
        </RailFact>
      </Band>
    </section>
  );
}

/**
 * One band of related properties.
 *
 * The heading is what makes the grouping legible; without it the rail is the
 * same six controls in a different order.
 */
function Band({
  title,
  note,
  children,
}: {
  title: string;
  note?: string;
  children: React.ReactNode;
}) {
  return (
    <section
      aria-label={title}
      data-slot="rail-band"
      className="flex flex-col gap-2 border-b border-border-subtle pb-3 last:border-b-0"
    >
      <div className="flex items-baseline gap-2">
        <h3 className="text-[13px] font-semibold text-fg-muted">{title}</h3>
        {note !== undefined && <span className="ms-auto text-xs text-fg-subtle">{note}</span>}
      </div>

      {children}
    </section>
  );
}

/**
 * THE EDITABLE EDGE.
 *
 * A 2px rule down the inside of every property: solid and accent-lit where the
 * value can be changed, dashed and inert where it cannot. It runs continuously
 * down the rail and then stops — and where it stops, facts begin.
 *
 * This is the clearest idea in the design system and it was in no screen. It
 * answers "can I change this?" before the agent tries, without a sentence, in
 * a place where the alternative is discovering the answer by clicking a
 * disabled control.
 *
 * It is a presentation of something already true — a locked row has no control
 * to focus — so it is `aria-hidden`. A screen reader learns the same fact from
 * the absence of a form control, which is a better signal than a described
 * line.
 */
function EditableEdge({ editable }: { editable: boolean }) {
  return (
    <span
      aria-hidden="true"
      data-slot="editable-edge"
      data-editable={editable}
      className={cn(
        "absolute inset-y-1 start-0 w-0.5 rounded-full",
        editable
          ? "bg-border-strong transition-colors group-focus-within:bg-accent-default group-hover:bg-accent-default"
          : "bg-[repeating-linear-gradient(180deg,var(--border-strong)_0_3px,transparent_3px_6px)]",
      )}
    />
  );
}

function RailSelect({
  label,
  value,
  options,
  disabled,
  onChange,
  children,
}: {
  label: string;
  value: string;
  options: Array<{ value: string; label: string }>;
  disabled: boolean;
  onChange: (value: string) => void;
  /** Rendered under the control — a face, a chip, whatever reads better. */
  children?: React.ReactNode;
}) {
  return (
    <label className="group relative flex flex-col gap-1 ps-3 text-sm">
      <EditableEdge editable={!disabled} />

      <span className="text-[13px] text-fg-muted">{label}</span>

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

      {children}
    </label>
  );
}

/** A property nobody can change, drawn with the dashed edge. */
function RailFact({
  label,
  note,
  children,
}: {
  label: string;
  note?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="relative flex flex-col gap-1 ps-3 text-sm" data-slot="rail-fact">
      <EditableEdge editable={false} />

      <span className="text-[13px] text-fg-muted">{label}</span>

      <div className="text-fg-default">{children}</div>

      {note !== undefined && <p className="text-xs text-fg-subtle">{note}</p>}
    </div>
  );
}
