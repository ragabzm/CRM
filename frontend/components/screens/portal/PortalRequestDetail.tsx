"use client";

import Link from "next/link";
import { useTranslations } from "next-intl";
import { useCallback, useState } from "react";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { ApiError } from "@/lib/api/errors";
import { useFreshQuery } from "@/lib/data/useFreshQuery";
import { useFormat } from "@/lib/format/useFormat";
import { getPortalRequest, replyToPortalRequest, reopenPortalRequest } from "@/lib/portal/api";

export interface PortalRequestDetailProps {
  requestId: string;
}

/**
 * One request, and the conversation about it.
 *
 * What is NOT here is as deliberate as what is: no agent's name, no priority,
 * no SLA countdown, no internal note. None of those are filtered out in this
 * component — the API never sends them, which is what makes the boundary real.
 */
export function PortalRequestDetail({ requestId }: PortalRequestDetailProps) {
  const t = useTranslations("portal.detail");
  const status = useTranslations("portal.requests.status");
  const format = useFormat();

  const fetcher = useCallback(() => getPortalRequest(requestId), [requestId]);
  const query = useFreshQuery(`portal-request:${requestId}`, fetcher);

  const [reply, setReply] = useState("");
  const [sending, setSending] = useState(false);
  const [notice, setNotice] = useState<{ tone: "success" | "error"; text: string } | null>(null);
  const [newRequestUrl, setNewRequestUrl] = useState<string | null>(null);

  const request = query.data;

  if (request === null && query.status === 404) {
    return <EmptyState headline={t("notFound")} description={t("notFoundBody")} />;
  }

  if (request === null) {
    return <p role="status">{t("thread")}</p>;
  }

  async function send(event: React.FormEvent) {
    event.preventDefault();

    if (reply.trim() === "") return;

    setSending(true);
    setNotice(null);

    try {
      await replyToPortalRequest(requestId, reply);

      setReply("");
      setNotice({ tone: "success", text: t("sent") });
      query.refetch();
    } catch {
      // The typed words stay on screen: the failure was not theirs.
      setNotice({ tone: "error", text: t("sendError") });
    } finally {
      setSending(false);
    }
  }

  async function reopen() {
    setNotice(null);
    setNewRequestUrl(null);

    try {
      await reopenPortalRequest(requestId);

      setNotice({ tone: "success", text: t("reopened") });
      query.refetch();
    } catch (caught) {
      /*
       * Past the window the API refuses with a 409 that carries the way
       * forward. Showing the refusal without it would leave a customer with a
       * closed door and nothing to do next — and the next thing they do is
       * email support about not being able to use support.
       */
      const url =
        caught instanceof ApiError
          ? (caught.problem as { new_request_url?: string } | null)?.new_request_url
          : undefined;

      setNotice({ tone: "error", text: t("reopenExpired") });
      setNewRequestUrl(url ?? "/portal/requests/new");
    }
  }

  return (
    <div className="flex flex-col gap-4" data-slot="portal-request-detail">
      <Link href="/portal/requests" className="text-sm underline">
        {t("back")}
      </Link>

      <header className="flex flex-col gap-1">
        <h1 dir="auto" className="break-words text-xl font-semibold text-fg-default">
          {request.subject}
        </h1>

        <p className="flex flex-wrap items-center gap-2 text-xs text-fg-muted">
          <span className="font-medium text-fg-default">{status(request.status)}</span>
          <bdi dir="ltr" className="num">
            {request.reference}
          </bdi>
        </p>
      </header>

      {notice !== null && (
        <FormAlert tone={notice.tone}>
          <span className="flex flex-wrap items-center gap-3">
            <span>{notice.text}</span>

            {newRequestUrl !== null && (
              <Link href={newRequestUrl} className="underline">
                {t("startNew")}
              </Link>
            )}
          </span>
        </FormAlert>
      )}

      <ol aria-label={t("thread")} className="flex flex-col gap-3">
        {/* The original question is the first thing said, and belongs in the
            thread rather than in a separate box above it. */}
        <Turn from="you" body={request.description} sentAt={request.created_at} format={format} />

        {request.messages.map((message) => (
          <Turn
            key={message.id}
            from={message.from}
            body={message.body}
            sentAt={message.sent_at}
            format={format}
          />
        ))}
      </ol>

      {request.status === "closed" ? (
        <form
          onSubmit={(event) => {
            event.preventDefault();
            void reopen();
          }}
        >
          <SubmitButton variant="secondary">{t("reopen")}</SubmitButton>
        </form>
      ) : (
        <form onSubmit={send} className="flex flex-col gap-3">
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-fg-default">{t("reply")}</span>

            <textarea
              dir="auto"
              rows={4}
              placeholder={t("replyPlaceholder")}
              value={reply}
              onChange={(event) => setReply(event.target.value)}
              className="w-full rounded-md border border-border-default bg-surface-base p-3 text-sm text-fg-default"
            />
          </label>

          <div>
            <SubmitButton variant="primary" pending={sending}>
              {t("send")}
            </SubmitButton>
          </div>
        </form>
      )}
    </div>
  );
}

function Turn({
  from,
  body,
  sentAt,
  format,
}: {
  from: "you" | "support";
  body: string;
  sentAt: string | null;
  format: ReturnType<typeof useFormat>;
}) {
  const t = useTranslations("portal.detail");

  return (
    <li
      data-slot="portal-turn"
      data-from={from}
      className="flex flex-col gap-1 rounded-md border border-border-default bg-surface-base p-3"
    >
      <p className="flex flex-wrap items-center gap-2 text-xs text-fg-muted">
        {/* Whose turn it was, in a word. Colour and side alone say nothing to a
            screen reader. */}
        <span className="font-medium text-fg-default">{t(from)}</span>

        {sentAt !== null && <time dateTime={sentAt}>{format.dateTime(sentAt)}</time>}
      </p>

      <p dir="auto" className="whitespace-pre-wrap break-words text-sm text-fg-default">
        {body}
      </p>
    </li>
  );
}
