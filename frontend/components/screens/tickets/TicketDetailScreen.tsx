"use client";

import { useTranslations } from "next-intl";
import { useCallback, useEffect, useState } from "react";

import { CustomerContextPanel } from "@/components/domain/CustomerContextPanel/CustomerContextPanel";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import { ConversationPanel } from "@/components/domain/TicketConversation/ConversationPanel";
import { TicketComposer } from "@/components/domain/TicketComposer/TicketComposer";
import { TicketPropertyRail } from "@/components/domain/TicketPropertyRail/TicketPropertyRail";
import { ApiError } from "@/lib/api/errors";
import {
  getCustomerContext,
  getTicket,
  type CustomerContext,
  type Ticket,
} from "@/lib/api/tickets";

export interface TicketDetailScreenProps {
  ticketId: string;
  categories: Array<{ id: number; name: string }>;
  assignees: Array<{ id: number; name: string }>;
  departments: Array<{ id: number; name: string }>;
  editable: boolean;
  onNavigate: (href: string) => void;
}

type Pane = "conversation" | "properties" | "customer";

/**
 * One workspace, so an agent can answer without leaving the ticket.
 *
 * Three regions on a wide screen; three tabs on a narrow one. The tabs are not
 * a lesser version — a phone showing three columns at 390px shows none of them
 * usably, and an agent answering from a phone is answering from a phone.
 *
 * Which region sits on which edge is decided entirely by writing direction:
 * the rail is grid column 1 in both, and the browser puts it on the left in
 * English and the right in Arabic. There is no mirroring code here to get
 * wrong.
 */
export function TicketDetailScreen({
  ticketId,
  categories,
  assignees,
  departments,
  editable,
  onNavigate,
}: TicketDetailScreenProps) {
  const t = useTranslations("ticket");

  const [ticket, setTicket] = useState<Ticket | null>(null);
  const [context, setContext] = useState<CustomerContext | null>(null);
  const [status, setStatus] = useState<"loading" | "ready" | "missing" | "forbidden" | "error">(
    "loading",
  );
  const [pane, setPane] = useState<Pane>("conversation");
  const [seedBody, setSeedBody] = useState<string | null>(null);
  const [conversationKey, setConversationKey] = useState(0);

  const load = useCallback(() => {
    let cancelled = false;

    void Promise.resolve().then(() => {
      if (!cancelled) setStatus("loading");
    });

    // In parallel. Two round trips in sequence is a second of staring at an
    // empty workspace for no reason.
    Promise.all([getTicket(ticketId), getCustomerContext(ticketId)])
      .then(([found, ctx]) => {
        if (cancelled) return;

        setTicket(found);
        setContext(ctx);
        setStatus("ready");
      })
      .catch((caught: unknown) => {
        if (cancelled) return;

        if (caught instanceof ApiError && caught.status === 403) {
          setStatus("forbidden");
        } else if (caught instanceof ApiError && caught.status === 404) {
          setStatus("missing");
        } else {
          setStatus("error");
        }
      });

    return () => {
      cancelled = true;
    };
  }, [ticketId]);

  useEffect(load, [load]);

  /** After a conflict: refetch everything EXCEPT the composer's state. */
  const reload = useCallback(() => {
    load();
    setConversationKey((n) => n + 1);
  }, [load]);

  if (status === "forbidden") {
    return <ForbiddenState headline={t("forbidden.title")} description={t("forbidden.body")} />;
  }

  if (status === "missing") {
    return <EmptyState headline={t("notFound.title")} description={t("notFound.body")} />;
  }

  if (status === "error") {
    return (
      <FormAlert tone="error" action={{ label: t("retryLoad"), onSelect: load }}>
        {t("loadError")}
      </FormAlert>
    );
  }

  if (ticket === null || context === null) {
    // Loading, not failed. This branch used to reuse the error copy, so a
    // perfectly healthy first render told the agent the ticket could not be
    // loaded — for as long as the round trip took.
    return <p role="status">{t("loading")}</p>;
  }

  const conversation = (
    <div className="flex flex-col gap-4">
      <ConversationPanel key={conversationKey} ticketId={ticketId} onEditFailed={setSeedBody} />

      {/*
        The composer is NOT remounted by a reload. An agent three sentences
        into a reply has not done anything wrong, and taking their words away
        to report someone else's edit would punish them for it.
      */}
      <TicketComposer
        ticketId={ticketId}
        seedBody={seedBody}
        onSent={() => {
          setSeedBody(null);
          setConversationKey((n) => n + 1);
        }}
      />
    </div>
  );

  const rail = (
    <TicketPropertyRail
      ticket={ticket}
      categories={categories}
      assignees={assignees}
      departments={departments}
      editable={editable}
      onChanged={setTicket}
      onReload={reload}
    />
  );

  const customer = (
    <CustomerContextPanel context={context} ticketId={ticketId} onOpenCustomer={onNavigate} />
  );

  return (
    <div className="flex flex-col gap-6" data-slot="ticket-detail">
      <header className="flex flex-wrap items-baseline gap-3">
        <h1 className="text-xl font-semibold text-fg-default">
          <bdi dir="auto">{ticket.subject}</bdi>
        </h1>
        <bdi dir="ltr" className="num text-sm text-fg-muted">
          {ticket.reference}
        </bdi>
      </header>

      {/* Below the tablet breakpoint: one pane at a time, chosen here. */}
      <div className="tablet:hidden">
        <SegmentedFilter
          label={t("tabs.conversation")}
          value={pane}
          options={[
            { value: "conversation", label: t("tabs.conversation") },
            { value: "properties", label: t("tabs.properties") },
            { value: "customer", label: t("tabs.customer") },
          ]}
          onChange={(value) => setPane(value as Pane)}
        />
      </div>

      <div className="tablet:hidden">
        {pane === "conversation" && conversation}
        {pane === "properties" && rail}
        {pane === "customer" && customer}
      </div>

      {/*
        Three columns from tablet up. Logical grid order only — the rail is
        column 1 in both writing modes and the browser decides which edge that
        is, so there is no second stylesheet for Arabic.
      */}
      <div className="hidden gap-6 tablet:grid tablet:grid-cols-[16rem_1fr_16rem]">
        {rail}
        <div className="min-w-0">{conversation}</div>
        {customer}
      </div>
    </div>
  );
}
