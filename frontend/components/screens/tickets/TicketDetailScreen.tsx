"use client";

import { useTranslations } from "next-intl";
import { useCallback, useEffect, useState } from "react";

import { CustomerContextPanel } from "@/components/domain/CustomerContextPanel/CustomerContextPanel";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import { StatusBadge, type TicketStatusName } from "@/components/domain/StatusBadge/StatusBadge";
import { TicketHeaderActions } from "@/components/domain/TicketHeaderActions/TicketHeaderActions";
import { ConversationPanel } from "@/components/domain/TicketConversation/ConversationPanel";
import { TicketComposer } from "@/components/domain/TicketComposer/TicketComposer";
import { AiSuggestionPanel } from "@/components/domain/AiSuggestionPanel/AiSuggestionPanel";
import { CategoryProposal } from "@/components/domain/CategoryProposal/CategoryProposal";
import { TicketPropertyRail } from "@/components/domain/TicketPropertyRail/TicketPropertyRail";
import { ApiError } from "@/lib/api/errors";
import {
  getCustomerContext,
  getTicket,
  updateTicketProperties,
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
      <ConversationPanel
        key={conversationKey}
        ticketId={ticketId}
        onEditFailed={setSeedBody}
        ticketChannel={ticket?.channel}
      />

      {/*
        The composer is NOT remounted by a reload. An agent three sentences
        into a reply has not done anything wrong, and taking their words away
        to report someone else's edit would punish them for it.
      */}
      <TicketComposer
        ticketId={ticketId}
        seedBody={seedBody}
        channelOpen={ticket?.channel_account_active ?? true}
        // The same list the Assignee select uses: active staff only, loaded
        // once for the screen rather than once per control.
        mentionable={assignees}
        onSent={() => {
          setSeedBody(null);
          setConversationKey((n) => n + 1);
        }}
      />
    </div>
  );

  /**
   * Confirming a proposed category.
   *
   * The ORDINARY command path — the same call the rail's own select makes,
   * carrying the version and attributed to the person who pressed the button.
   * Never to System and never to the AI: the history has to say who decided,
   * and the answer is always a person.
   *
   * A stale version is refused rather than merged, exactly as it is anywhere
   * else on this screen: the reload offer is the rail's, and there is no diff
   * and no keep-mine picker here either.
   */
  async function confirmCategory(categoryId: number): Promise<void> {
    if (ticket === null) {
      return;
    }

    try {
      setTicket(
        await updateTicketProperties(ticket.id, ticket.version, { category_id: categoryId }),
      );
    } catch {
      // The ticket moved on. Reloading is the offer, and the rail makes it.
      reload();
    }
  }

  const rail = (
    <div className="flex flex-col gap-6">
      <TicketPropertyRail
        ticket={ticket}
        categories={categories}
        assignees={assignees}
        departments={departments}
        editable={editable}
        onChanged={setTicket}
        onReload={reload}
      />

      {/*
        BESIDE the category field, never inside it. A pre-filled field is an
        application: the agent sees a value, assumes somebody chose it, and
        saves. Confirming here runs the ordinary category change with their own
        name on it.
      */}
      {ticket !== null && editable && (
        <CategoryProposal
          ticketId={ticketId}
          current={ticket.category_id}
          onConfirm={(categoryId) => void confirmCategory(categoryId)}
        />
      )}

      {/*
        The assistant, in the rail and never in the thread. The conversation
        has three treatments — customer, agent, internal note — and an AI draft
        is not a fourth: it lives here and in the composer.
      */}
      {ticket !== null && (
        <AiSuggestionPanel
          ticketId={ticketId}
          onUseDraft={setSeedBody}
          onInsertArticle={(article) => setSeedBody(article.title ?? "")}
        />
      )}
    </div>
  );

  const customer = (
    <CustomerContextPanel context={context} ticketId={ticketId} onOpenCustomer={onNavigate} />
  );

  return (
    <div className="flex flex-col gap-6" data-slot="ticket-detail">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex flex-wrap items-center gap-3">
          {/*
            The reference as a CHIP before the title, not grey text beside it.
            It is the thing an agent reads out on the phone and pastes into a
            note, so it needs an edge somebody can aim at.
          */}
          <bdi
            dir="ltr"
            className="num rounded-sm border border-border-default bg-surface-sunken px-2 py-0.5 text-xs text-fg-muted"
          >
            {ticket.reference}
          </bdi>

          <h1 className="text-xl font-semibold text-fg-default">
            <bdi dir="auto">{ticket.subject}</bdi>
          </h1>

          <StatusBadge status={ticket.status as TicketStatusName} />
        </div>

        {/*
          Resolving used to be a `<select>` in the rail, three columns away and
          weighted exactly like "Category" — the most-used decision on the
          busiest screen, hidden inside a dropdown.
        */}
        <TicketHeaderActions
          ticket={ticket}
          editable={editable}
          onChanged={setTicket}
          onReload={reload}
        />
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
      <div /*
          The rail is 20rem, not 16.
          At 256px the live-state band puts status and service level side by
          side inside it, and "43d 7h over" broke across three lines while
          "due Sep 4, 2026, 5:39 AM" broke across two. The workspace mockup
          gives the rail ~340px for exactly this reason. The customer panel
          stays at 16rem: it holds a name and four short readings.
        */
        className="hidden gap-6 tablet:grid tablet:grid-cols-[20rem_1fr_16rem]"
      >
        {rail}
        <div className="min-w-0">{conversation}</div>
        {customer}
      </div>
    </div>
  );
}
