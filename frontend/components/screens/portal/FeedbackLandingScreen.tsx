"use client";

import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { useState } from "react";

import { SatisfactionPrompt } from "@/components/domain/SatisfactionPrompt/SatisfactionPrompt";
import { rateByInvitation } from "@/lib/portal/api";

/**
 * Where the tap in the resolution email lands.
 *
 * The answer is ALREADY RECORDED by the time this renders — the link did that
 * on the server before redirecting here. This page exists for the second half
 * of the promise: the optional comment, offered after the rating and never
 * before it. Somebody who closes the tab now has given a complete answer.
 *
 * No sign-in, and nothing here reveals anything a sign-in would. It shows the
 * answer that was just given and a box to add words to it. Not the
 * conversation, not the customer's other requests, not who handled it — the
 * link grants one thing and this page can do exactly that one thing.
 */
export function FeedbackLandingScreen() {
  const t = useTranslations("portal.feedback");
  const params = useSearchParams();

  const ticket = params.get("ticket");
  const verdict = params.get("verdict");
  const expires = params.get("expires");
  const signature = params.get("signature");

  const usable =
    ticket !== null &&
    (verdict === "up" || verdict === "down") &&
    expires !== null &&
    signature !== null;

  const [comment, setComment] = useState<string | null>(null);

  if (!usable) {
    /*
     * A link that arrived without its signature — forwarded as plain text and
     * broken by a mail client, most often. Said plainly, because the customer
     * did nothing wrong and the portal is a real way through.
     */
    return (
      <section className="mx-auto flex max-w-md flex-col gap-3">
        <h1 className="text-lg font-semibold text-fg-default">{t("linkBrokenTitle")}</h1>
        <p className="text-sm text-fg-muted">{t("linkBroken")}</p>
      </section>
    );
  }

  return (
    <section className="mx-auto flex max-w-md flex-col gap-4">
      {/* The page's own name. The confirmation belongs to the prompt below,
          which already says it — two thank-yous stacked read as a mistake. */}
      <h1 className="text-lg font-semibold text-fg-default">{t("landingTitle")}</h1>

      <SatisfactionPrompt
        satisfaction={verdict === "up"}
        satisfactionComment={comment}
        canRate
        onRate={async (positive, withComment) => {
          const saved = await rateByInvitation(
            { ticket, verdict: positive ? "up" : "down", expires, signature },
            withComment,
          );

          setComment(saved.satisfaction_comment);
        }}
      />
    </section>
  );
}
