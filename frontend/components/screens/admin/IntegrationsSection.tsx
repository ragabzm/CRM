"use client";

import { useTranslations } from "next-intl";

import { ErpTestExchange } from "@/components/domain/ErpTestExchange/ErpTestExchange";
import { ExchangeLogTable } from "@/components/domain/ExchangeLogTable/ExchangeLogTable";

import { Panel } from "./Panel";
import { SettingsGroup } from "./SettingsGroup";
import { useSettings } from "./useSettings";

/**
 * One connector, its configuration, its test action and the log.
 *
 * There is NO CONNECTOR LIST here, and that is deliberate rather than
 * unfinished. A catalogue of named vendors is a bet on which ERP the first
 * customer runs, placed before anybody has asked them — and every entry in it
 * is a vendor whose API changes are now this product's problem. One generic
 * REST adapter answers the same question without the catalogue.
 *
 * Nor is there an organisation mapping row: a mapped contact becomes a
 * customer, and there is nothing above a customer to map to.
 */
const CONNECTION_KEYS = [
  "integrations.erp.enabled",
  "integrations.erp.endpoint",
  "integrations.erp.credential",
  "integrations.erp.auth_header",
  "integrations.erp.timeout_seconds",
];

/** What moves, and when. Configuration, not a deployment. */
const SYNC_KEYS = ["integrations.erp.direction", "integrations.erp.trigger"];

export function IntegrationsSection() {
  const t = useTranslations("admin.integrations");
  const { settings, save } = useSettings();

  return (
    <div className="flex flex-col gap-6">
      <Panel title={t("connection")} hint={t("connectionHint")}>
        <SettingsGroup keys={CONNECTION_KEYS} settings={settings} save={save} />

        <div className="border-t border-border-subtle pt-4">
          <ErpTestExchange />
        </div>
      </Panel>

      <Panel title={t("sync")} hint={t("syncHint")}>
        <SettingsGroup keys={SYNC_KEYS} settings={settings} save={save} />
      </Panel>

      <Panel title={t("fieldMap")} hint={t("fieldMapHint")}>
        <SettingsGroup keys={["integrations.erp.field_map"]} settings={settings} save={save} />
      </Panel>

      <Panel title={t("log")} hint={t("logHint")}>
        <ExchangeLogTable />
      </Panel>

      <Panel title={t("retention")} hint={t("retentionHint")}>
        <SettingsGroup
          keys={["integrations.exchange_log.retention_days"]}
          settings={settings}
          save={save}
        />
      </Panel>
    </div>
  );
}
