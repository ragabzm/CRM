"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { testErpExchange, type ErpTestResult } from "@/lib/api/admin";
import { ApiError } from "@/lib/api/errors";

/**
 * "Does this configuration actually work?"
 *
 * This runs a REAL exchange down the same adapter path a sync uses — same
 * headers, same credential, same timeout, and it leaves a row in the exchange
 * log like any other call. A connection check would go green on a reachable
 * host with a rejected credential, and then fail at 3am on the first real
 * sync; a passing test here means a working exchange.
 *
 * When it fails it shows WHERE it went, HOW LONG it took and WHAT came back.
 * "Failed" on its own is a result an administrator can do nothing with, and
 * the three things they need to tell a wrong endpoint from a wrong credential
 * from a firewall are exactly these.
 */
export function ErpTestExchange() {
  const t = useTranslations("admin.integrations");

  const [running, setRunning] = useState(false);
  const [result, setResult] = useState<ErpTestResult | null>(null);
  const [refused, setRefused] = useState<string | null>(null);

  async function run(event: React.FormEvent) {
    event.preventDefault();

    setRunning(true);
    setResult(null);
    setRefused(null);

    try {
      setResult(await testErpExchange());
    } catch (caught) {
      // A refusal before the call — no endpoint configured yet — is a
      // different thing from an exchange that ran and failed.
      setRefused(
        caught instanceof ApiError
          ? (caught.problem?.detail ?? t("testRefused"))
          : t("testRefused"),
      );
    } finally {
      setRunning(false);
    }
  }

  return (
    <form onSubmit={run} data-slot="erp-test-exchange" className="flex flex-col gap-3">
      <div>
        <SubmitButton variant="secondary" pending={running} pendingLabel={t("testRunning")}>
          {t("test")}
        </SubmitButton>
      </div>

      <p className="text-sm text-fg-muted">{t("testHint")}</p>

      {refused !== null && <FormAlert tone="error">{refused}</FormAlert>}

      {result !== null && (
        <FormAlert tone={result.succeeded ? "success" : "error"}>
          <span className="flex flex-col gap-1">
            <span className="font-medium">
              {result.succeeded ? t("testSucceeded") : t("testFailedTitle")}
            </span>

            {/* The endpoint reached, so a typo is visible rather than inferred. */}
            <span className="break-all">{t("testEndpoint", { endpoint: result.endpoint })}</span>

            {result.status !== null && (
              <span>{t("testStatus", { status: String(result.status) })}</span>
            )}

            {result.duration_ms !== null && (
              <span>{t("testDuration", { ms: String(result.duration_ms) })}</span>
            )}

            {result.error !== null && <span className="break-all">{result.error}</span>}
          </span>
        </FormAlert>
      )}
    </form>
  );
}
