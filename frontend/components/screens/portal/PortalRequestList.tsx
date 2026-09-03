"use client";

import { useTranslations } from "next-intl";
import { useCallback } from "react";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { useFreshQuery } from "@/lib/data/useFreshQuery";
import { useFormat } from "@/lib/format/useFormat";
import { listPortalRequests, type PortalRequestSummary } from "@/lib/portal/api";

export interface PortalRequestListProps {
  onOpen: (id: string) => void;
}

/**
 * Everything a customer has asked us.
 *
 * A stack of cards, not a table. At 320 px a table either scrolls sideways or
 * drops the columns that made it a table — and a customer with four requests
 * does not need to sort, filter or compare them.
 *
 * The status words are the customer's, not the desk's: "With us" and "Waiting
 * for you" say whose turn it is, which is the only thing a customer actually
 * wants from a status.
 */
export function PortalRequestList({ onOpen }: PortalRequestListProps) {
  const t = useTranslations("portal.requests");
  const format = useFormat();

  const fetcher = useCallback(() => listPortalRequests(), []);
  const { data, loading, stale, refetch } = useFreshQuery("portal-requests", fetcher);

  if (data === null && stale) {
    return (
      <FormAlert tone="error" action={{ label: t("retry"), onSelect: refetch }}>
        {t("loadError")}
      </FormAlert>
    );
  }

  const requests = data ?? [];

  if (requests.length === 0 && !loading) {
    return <EmptyState headline={t("empty")} description={t("emptyBody")} />;
  }

  return (
    <ul className="flex flex-col gap-3" data-slot="portal-request-list">
      {requests.map((request) => (
        <li key={request.id}>
          <button
            type="button"
            onClick={() => onOpen(request.id)}
            data-status={request.status}
            className="flex w-full flex-col gap-1 rounded-md border border-border-default bg-surface-base p-3 text-start hover:bg-surface-hover"
          >
            {/* Wraps, never clips: at 320 px a truncated subject hides the
                words that told them which request this is. */}
            <span dir="auto" className="break-words font-medium text-fg-default">
              {request.subject}
            </span>

            <span className="flex flex-wrap items-center gap-2 text-xs text-fg-muted">
              <StatusWord status={request.status} />

              {/* A reference is an identifier: forced LTR so a customer can
                  read it out over the phone in either language. */}
              <bdi dir="ltr" className="num">
                {request.reference}
              </bdi>

              {request.updated_at !== null && (
                <time dateTime={request.updated_at}>{format.dateTime(request.updated_at)}</time>
              )}
            </span>
          </button>
        </li>
      ))}
    </ul>
  );
}

/** The status in words a customer recognises. */
function StatusWord({ status }: { status: PortalRequestSummary["status"] }) {
  const t = useTranslations("portal.requests.status");

  return (
    <span data-slot="request-status" className="font-medium text-fg-default">
      {t(status)}
    </span>
  );
}
