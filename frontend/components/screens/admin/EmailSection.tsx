"use client";

import { useTranslations } from "next-intl";

import { AsyncAction } from "@/components/domain/AsyncAction/AsyncAction";
import { sendTestEmail } from "@/lib/api/admin";

import { Panel } from "./Panel";
import { SettingsGroup } from "./SettingsGroup";
import { useSettings } from "./useSettings";

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
      <Panel title={t("mailbox")} hint={t("mailboxHint")}>
        <SettingsGroup keys={MAILBOX_KEYS} settings={settings} save={save} />

        <div className="border-t border-border-subtle pt-4">
          <AsyncAction
            label={t("sendTest")}
            hint={t("sendTestHint")}
            onRun={sendTestEmail}
            successMessage={t("testQueued")}
            failureMessage={t("testFailed")}
          />
        </div>
      </Panel>

      <Panel title={t("acknowledgement")} hint={t("acknowledgementHint")}>
        <SettingsGroup keys={["email.acknowledgement_template"]} settings={settings} save={save} />
      </Panel>

      <Panel title={t("log")}>
        <p className="text-sm text-fg-muted">{t("logPlaceholder")}</p>
      </Panel>
    </div>
  );
}
