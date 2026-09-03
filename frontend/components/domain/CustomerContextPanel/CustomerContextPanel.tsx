"use client";

import { useTranslations } from "next-intl";

import type { CustomerContext } from "@/lib/api/tickets";
import { useFormat } from "@/lib/format/useFormat";

export interface CustomerContextPanelProps {
  context: CustomerContext;
  ticketId: string;
  onOpenCustomer: (href: string) => void;
}

/**
 * Who this ticket is for.
 *
 * An agent answers a person, not a ticket. Knowing this is their fourth request
 * this month changes the reply — and knowing it has to cost nothing, or the
 * panel gets collapsed and the agent stops knowing.
 */
export function CustomerContextPanel({
  context,
  ticketId,
  onOpenCustomer,
}: CustomerContextPanelProps) {
  const t = useTranslations("ticket.context");
  const format = useFormat();

  /*
   * The way back is part of the link.
   *
   * An agent who opens the customer record mid-reply must be able to return to
   * the ticket they were answering. Carrying it in the URL rather than in
   * memory means the back trip survives a reload, a new tab, or a link pasted
   * to a colleague.
   */
  const href = `/customers/${encodeURIComponent(context.customer_id)}?returnTo=${encodeURIComponent(`/tickets/${ticketId}`)}`;

  return (
    <section
      data-slot="customer-context"
      aria-label={t("title")}
      className="flex flex-col gap-3 text-sm"
    >
      <h2 className="text-base font-semibold text-fg-default">{t("title")}</h2>

      <p className="font-medium text-fg-default">
        <bdi dir="auto">{context.full_name}</bdi>
      </p>

      <p className="text-xs text-fg-muted">
        {/* A reference is an identifier, not prose: forced LTR so it reads the
            same in both writing directions. */}
        <bdi dir="ltr" className="num">
          {context.reference}
        </bdi>
      </p>

      <dl className="flex flex-col gap-2">
        <Row label={t("department")} value={context.department?.name ?? "—"} />
        <Row label={t("openTickets")} value={format.number(context.open_ticket_count)} />
        <Row
          label={t("recentTickets", { days: context.recent_window_days })}
          value={format.number(context.recent_ticket_count)}
        />
        <Row
          label={t("lastInteraction")}
          value={
            context.last_interaction_at === null
              ? t("never")
              : format.dateTime(context.last_interaction_at)
          }
        />
      </dl>

      <button
        type="button"
        onClick={() => onOpenCustomer(href)}
        className="text-start text-sm font-medium text-accent-text underline"
      >
        {t("openRecord")}
      </button>
    </section>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-baseline justify-between gap-3">
      <dt className="text-fg-muted">{label}</dt>
      <dd className="font-medium text-fg-default">
        <bdi dir="auto">{value}</bdi>
      </dd>
    </div>
  );
}
