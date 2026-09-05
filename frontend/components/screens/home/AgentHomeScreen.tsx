"use client";

import { useTranslations } from "next-intl";
import { useCallback, useMemo, useState } from "react";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { MentionList } from "@/components/domain/MentionList/MentionList";
import { TaskComposer } from "@/components/domain/TaskComposer/TaskComposer";
import { TaskList } from "@/components/domain/TaskList/TaskList";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { RowSkeleton } from "@/components/domain/RowSkeleton/RowSkeleton";
import { TicketListTable } from "@/components/domain/TicketList/TicketListTable";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  createTask,
  listMentions,
  listTasks,
  markMentionRead,
  setTaskCompletion,
} from "@/lib/api/personal";
import { listTickets, ticketCounts, type Ticket, type TicketListParams } from "@/lib/api/tickets";
import { useFreshQuery } from "@/lib/data/useFreshQuery";
import { useFormat } from "@/lib/format/useFormat";

import { CountsStrip } from "./CountsStrip";

export interface AgentHomeScreenProps {
  currentUserId: number | null;
  onOpen: (id: string) => void;
  /** Which tab the address bar asked for, if any. */
  initialTab?: HomeTab;
}

/**
 * The three things an agent lands on.
 *
 * Tasks and reminders are a TAB, not a destination: no sidebar entry, no route
 * of their own, no global task list. That is a settled decision rather than a
 * layout preference — a Tasks section beside Tickets invites a second backlog
 * with its own queue, its own owner and its own arguments about priority.
 */
export const HOME_TABS = ["queue", "tasks", "mentions"] as const;

export type HomeTab = (typeof HOME_TABS)[number];

const REFETCH_MS = 30_000;

/**
 * The reasons a ticket is on this screen, worst first.
 *
 * The order is the screen's argument: a breached ticket is not the same kind
 * of problem as one waiting on a customer, and putting them in one list sorted
 * by a column asks the agent to work out which is which.
 */
const GROUPS = ["breached", "atRisk", "waiting", "rest"] as const;

type GroupKey = (typeof GROUPS)[number];

function groupKeyFor(ticket: Ticket): GroupKey {
  if (ticket.sla?.state === "breached") return "breached";
  if (ticket.sla?.state === "at_risk") return "atRisk";
  // Pending means the ball is with the customer — real work, but not the
  // agent's to do right now.
  if (ticket.status === "pending") return "waiting";

  return "rest";
}

/**
 * The queue in group order, plus the headings to draw inside it.
 *
 * Empty groups are dropped — a heading with nothing under it is noise.
 */
function groupedQueue(
  tickets: Ticket[],
  label: (key: GroupKey) => string,
  note: (key: GroupKey) => string,
): {
  rows: Ticket[];
  groups: Array<{ id: string; label: string; note: string; rowIds: string[] }>;
} {
  const groups = GROUPS.map((key) => ({
    key,
    tickets: tickets.filter((ticket) => groupKeyFor(ticket) === key),
  })).filter((group) => group.tickets.length > 0);

  return {
    rows: groups.flatMap((group) => group.tickets),
    groups: groups.map((group) => ({
      id: group.key,
      label: label(group.key),
      note: note(group.key),
      rowIds: group.tickets.map((ticket) => ticket.id),
    })),
  };
}

/**
 * Where an agent lands, and what they should do next.
 *
 * The counts strip answers "how much is there", the queue answers "what
 * first". Both refresh on the same interval so they cannot disagree with each
 * other on screen, and both keep their last good answer through a failure —
 * an agent working a queue should not watch it empty itself when the wifi
 * blinks.
 *
 * Ordering is deliberate and NOT alphabetical: priority, then age. When the
 * SLA module lands it becomes the first key — the sort is expressed as a
 * server-side sort so that change is one string, not a rewrite.
 */
export function AgentHomeScreen({ currentUserId, onOpen, initialTab }: AgentHomeScreenProps) {
  const t = useTranslations("home");
  // The list's own copy for load states, so both surfaces say the same thing.
  const list = useTranslations("tickets");
  const format = useFormat();

  const queueParams: TicketListParams = {
    status: ["open", "pending"],
    ...(currentUserId === null ? {} : { assignee_id: [currentUserId] }),
    /*
     * Priority first, then oldest. SLA urgency becomes the leading key in
     * Story 5.3; until the column exists, sorting by it would be sorting by
     * nothing and would silently reorder the queue when it appears.
     */
    sort: "priority",
    direction: "desc",
    per_page: 25,
  };

  const queueFetcher = useCallback(() => listTickets(queueParams), [currentUserId]); // eslint-disable-line react-hooks/exhaustive-deps
  const countsFetcher = useCallback(() => ticketCounts(), []);

  const queue = useFreshQuery(`queue:${currentUserId ?? "anon"}`, queueFetcher, {
    refetchInterval: REFETCH_MS,
    refetchOnWindowFocus: true,
  });

  const counts = useFreshQuery("counts", countsFetcher, {
    refetchInterval: REFETCH_MS,
    refetchOnWindowFocus: true,
  });

  const [tab, setTab] = useState<HomeTab>(initialTab ?? "queue");

  /*
   * The other two tabs fetch their rows, but NOT their badge counts — those
   * ride along with the strip above, in one response, on one interval. A badge
   * that fetched itself would be a third request on the busiest screen in the
   * product, and could disagree with the list it counts.
   */
  const tasksFetcher = useCallback(() => listTasks(), []);
  const mentionsFetcher = useCallback(() => listMentions(), []);

  const tasks = useFreshQuery("tasks", tasksFetcher, { refetchInterval: REFETCH_MS });
  const mentions = useFreshQuery("mentions", mentionsFetcher, { refetchInterval: REFETCH_MS });

  const personal = counts.data?.personal ?? { tasks: 0, tasks_overdue: 0, mentions: 0 };

  /*
   * One refresh after a write, not two optimistic copies.
   *
   * Ticking a task changes both the list and the badge above it, and the badge
   * lives in a different response. Refetching both is the only way they cannot
   * drift apart on screen — and a tick is rare enough that the round trip is
   * invisible.
   */
  const refreshPersonal = useCallback(() => {
    // `refetch` starts the request and returns; the rows and the badge land
    // together on the next settled answer.
    tasks.refetch();
    counts.refetch();
  }, [tasks.refetch, counts.refetch]); // eslint-disable-line react-hooks/exhaustive-deps

  const refreshMentions = useCallback(() => {
    mentions.refetch();
    counts.refetch();
  }, [mentions.refetch, counts.refetch]); // eslint-disable-line react-hooks/exhaustive-deps

  /*
   * DERIVED from the data, not stored beside it.
   *
   * `counts.data` is a new object on every settled refetch, so this recomputes
   * exactly once per answer — the stamp says when the numbers under it were
   * true, and cannot drift away from them on a timer of its own. Reading
   * `new Date()` straight in the body instead would change on every paint.
   */
  // eslint-disable-next-line react-hooks/exhaustive-deps -- the data IS the dependency
  const now = useMemo(() => new Date(), [counts.data, queue.data]);

  const rows = queue.data?.data ?? [];

  const grouped = groupedQueue(
    rows,
    (key) => t(`queue.groups.${key}`),
    (key) => t(`queue.groupNotes.${key}`),
  );

  /*
   * The labels arrive with the rows they belong to.
   *
   * They used to be props, and no page ever passed them — so the Assignee and
   * Category columns rendered a dash on every row, and an assigned ticket was
   * indistinguishable from an unclaimed one. Reading them off the same
   * response the rows came from is what makes that impossible to forget.
   */
  const assigneeNames = queue.data?.included?.assignees ?? {};
  const categoryNames = queue.data?.included?.categories ?? {};

  return (
    <div className="flex flex-col gap-6" data-slot="agent-home">
      <div className="flex flex-col gap-0.5">
        <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>

        {/*
          When "now" is. Every number on this screen is a reading taken at a
          moment — "3 breached" is only meaningful next to the time it was
          true — and the strip refreshes on its own every thirty seconds, so
          without a timestamp the reader cannot tell a stale screen from a
          quiet queue.
        */}
        <p className="text-xs text-fg-muted">{format.dateTime(now)}</p>
      </div>

      <CountsStrip counts={counts.data} currentUserId={currentUserId} />

      <Tabs value={tab} onValueChange={(next) => setTab(next as HomeTab)}>
        <TabsList aria-label={t("tabs.label")}>
          <TabsTrigger value="queue">{t("tabs.queue")}</TabsTrigger>
          <TabsTrigger value="tasks">
            {/*
              The count is part of the label, not a decoration beside it, so a
              screen reader announces "Tasks and reminders, 4" rather than
              reading a bare number after the tab name.
            */}
            {t("tabs.tasks", { count: personal.tasks })}
          </TabsTrigger>
          <TabsTrigger value="mentions">
            {t("tabs.mentions", { count: personal.mentions })}
          </TabsTrigger>
        </TabsList>

        <TabsContent value="tasks" className="flex flex-col gap-4">
          <TaskComposer
            onCreate={async (input) => {
              await createTask(input);
              refreshPersonal();
            }}
          />

          {tasks.loading && tasks.data === null ? (
            <RowSkeleton label={list("loading")} rows={4} />
          ) : (
            <TaskList
              tasks={tasks.data ?? []}
              onToggle={async (id, completed) => {
                await setTaskCompletion(id, completed);
                refreshPersonal();
              }}
              onOpenTicket={onOpen}
            />
          )}
        </TabsContent>

        <TabsContent value="mentions">
          {mentions.loading && mentions.data === null ? (
            <RowSkeleton label={list("loading")} rows={3} />
          ) : (
            <MentionList
              mentions={mentions.data ?? []}
              /*
               * At the note, not the top of the thread. The whole content of a
               * mention is "come and read this sentence".
               */
              onOpen={(ticketId, messageId) => onOpen(`${ticketId}#note-${messageId}`)}
              onMarkRead={async (id) => {
                await markMentionRead(id);
                refreshMentions();
              }}
            />
          )}
        </TabsContent>

        <TabsContent value="queue" className="flex flex-col gap-3">
          <div className="flex flex-wrap items-baseline gap-2">
            <h2 className="text-base font-semibold text-fg-default">{t("queue.title")}</h2>

            {/*
            The ordering, said out loud. The queue has always been sorted by
            urgency and then age, and nothing on screen mentioned it — so the
            order looked arbitrary, and an agent working top-down had no reason
            to trust that top meant "first".
          */}
            <p className="text-xs text-fg-subtle">{t("queue.ordering")}</p>
          </div>

          {/*
          Quiet, and the rows stay put. A banner that shouted would interrupt
          work over a blip the next interval will fix by itself.
        */}
          {queue.stale && queue.data !== null && (
            <p role="status" className="text-xs text-fg-muted">
              {list("stale")}
            </p>
          )}

          {queue.stale && queue.data === null && (
            <FormAlert tone="error" action={{ label: list("retry"), onSelect: queue.refetch }}>
              {list("loadError")}
            </FormAlert>
          )}

          {queue.loading && queue.data === null ? (
            /*
            The same rule as the list: an empty state on first paint is an
            answer to a question the request has not returned yet.
          */
            <RowSkeleton label={list("loading")} rows={5} />
          ) : rows.length === 0 ? (
            <EmptyState headline={t("queue.empty")} description={t("queue.emptyBody")} />
          ) : (
            /*
            ONE table with spanning group headings.
            Rendering a DataTable per group gave each group its own search box
            and its own column picker, let the columns settle to different
            widths, and repeated the header row — so a grouped queue read as
            two unrelated tables instead of one list ordered by why each
            ticket needs attention.
          */
            <TicketListTable
              tickets={grouped.rows}
              caption={t("queue.title")}
              onOpen={onOpen}
              assigneeNames={assigneeNames}
              categoryNames={categoryNames}
              groups={grouped.groups}
            />
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}
