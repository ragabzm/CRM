"use client";

import { useTranslations } from "next-intl";

import { EmailTestSend } from "@/components/domain/EmailTestSend/EmailTestSend";
import { MailLogTable } from "@/components/domain/MailLogTable/MailLogTable";
import { MailQuarantineTable } from "@/components/domain/MailQuarantine/MailQuarantineTable";

import { Panel } from "./Panel";
import { SettingsGroup } from "./SettingsGroup";
import { useSettings } from "./useSettings";

/** The channel itself: on or off, and through whom. */
const CHANNEL_KEYS = [
  "email.enabled",
  "email.provider",
  "email.from_address",
  "email.from_name",
  "email.domain",
  "email.provider_credential",
];

/** Where a customer's email arrives. */
const INBOUND_KEYS = [
  "email.inbound.enabled",
  "email.inbound.provider",
  "email.inbound.webhook_secret",
];

const MAILBOX_KEYS = [
  "email.mailbox.host",
  "email.mailbox.port",
  "email.mailbox.username",
  "email.mailbox.password",
  "email.mailbox.encryption",
];

export function EmailSection() {
  const t = useTranslations("admin.email");
  const { settings, save } = useSettings();

  return (
    <div className="flex flex-col gap-6">
      <Panel title={t("channel")} hint={t("channelHint")}>
        <SettingsGroup keys={CHANNEL_KEYS} settings={settings} save={save} />

        <div className="border-t border-border-subtle pt-4">
          {/*
            A real send, not a connection check. The only honest answer to
            "does this work" is something arriving in an inbox.
          */}
          <EmailTestSend />
        </div>
      </Panel>

      <Panel title={t("mailbox")} hint={t("mailboxHint")}>
        <SettingsGroup keys={MAILBOX_KEYS} settings={settings} save={save} />
      </Panel>

      <Panel title={t("acknowledgement")} hint={t("acknowledgementHint")}>
        <SettingsGroup keys={["email.acknowledgement_template"]} settings={settings} save={save} />
      </Panel>

      <Panel title={t("inbound")} hint={t("inboundHint")}>
        <SettingsGroup keys={INBOUND_KEYS} settings={settings} save={save} />
      </Panel>

      <Panel title={t("quarantine")} hint={t("quarantineHint")}>
        <MailQuarantineTable />
      </Panel>

      <Panel title={t("log")} hint={t("logHint")}>
        <MailLogTable />
      </Panel>
    </div>
  );
}
