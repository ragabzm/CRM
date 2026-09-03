"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { sendTestEmail } from "@/lib/api/admin";
import { ApiError } from "@/lib/api/errors";

/**
 * "Does this configuration actually work?"
 *
 * The only honest way to answer that is to send something. A green tick because
 * the fields are filled in tells an administrator the channel is fine right up
 * until the first customer does not get a reply.
 *
 * When it fails, the PROVIDER's own words are shown. "Sending failed" is
 * useless — an administrator cannot tell an expired API key from a blocked port
 * from a rejected sender domain — and the provider already diagnosed it.
 */
export function EmailTestSend() {
  const t = useTranslations("admin.email");

  const [to, setTo] = useState("");
  const [sending, setSending] = useState(false);
  const [sentTo, setSentTo] = useState<string | null>(null);
  const [failure, setFailure] = useState<{ detail: string; retryable: boolean } | null>(null);

  async function run(event: React.FormEvent) {
    event.preventDefault();

    setSending(true);
    setSentTo(null);
    setFailure(null);

    try {
      const result = await sendTestEmail(to);

      setSentTo(result.sent_to);
    } catch (caught) {
      const problem = caught instanceof ApiError ? caught.problem : null;

      setFailure({
        detail: problem?.detail ?? t("testFailed"),
        // Whether trying the same thing again could help — which is the
        // administrator's actual next decision.
        retryable: Boolean((problem as { retryable?: boolean } | null)?.retryable),
      });
    } finally {
      setSending(false);
    }
  }

  return (
    <form onSubmit={run} data-slot="email-test-send" className="flex flex-col gap-3">
      <FormField
        label={t("sendTestTo")}
        name="to"
        type="email"
        value={to}
        hint={t("sendTestToHint")}
        onChange={(event) => setTo(event.target.value)}
      />

      <div>
        <SubmitButton variant="secondary" pending={sending} pendingLabel={t("sendTest")}>
          {t("sendTest")}
        </SubmitButton>
      </div>

      {sentTo !== null && (
        <FormAlert tone="success">{t("testSent", { address: sentTo })}</FormAlert>
      )}

      {failure !== null && (
        <FormAlert tone="error">
          <span className="flex flex-col gap-1">
            <span className="font-medium">{t("testFailedTitle")}</span>

            {/* Verbatim. This is the difference between a five-minute fix and
                a support ticket. */}
            <span dir="auto" className="break-words">
              {failure.detail}
            </span>

            <span className="text-xs">
              {failure.retryable ? t("testRetryable") : t("testPermanent")}
            </span>
          </span>
        </FormAlert>
      )}
    </form>
  );
}
