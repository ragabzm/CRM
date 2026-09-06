"use client";

import { useTranslations } from "next-intl";

import { useFormat } from "@/lib/format/useFormat";

import { Panel } from "./Panel";
import { SettingsGroup } from "./SettingsGroup";
import { useSettings } from "./useSettings";

export function PlatformSection() {
  const t = useTranslations("admin.platform");
  const format = useFormat();
  const { settings, save } = useSettings();

  const cap = settings["platform.attachments.max_bytes"];
  const capBytes = typeof cap?.value === "number" ? cap.value : null;

  return (
    <div className="flex flex-col gap-6">
      <Panel title={t("attachments")}>
        <SettingsGroup
          keys={["platform.attachments.allowed_mime_types"]}
          settings={settings}
          labels={{ "platform.attachments.allowed_mime_types": t("attachmentTypes") }}
          save={save}
        />
        <p className="text-xs text-fg-muted">{t("attachmentTypesHint")}</p>

        <SettingsGroup
          keys={["platform.attachments.max_bytes"]}
          settings={settings}
          labels={{ "platform.attachments.max_bytes": t("attachmentSize") }}
          save={save}
        />
        {capBytes !== null && (
          // The stored value is bytes because that is what an upload check
          // compares against; the reader is shown what that means.
          <p className="text-xs text-fg-muted">{format.fileSize(capBytes)}</p>
        )}
      </Panel>

      {/*
        Branding: three values, and the console says where they stop.
      */}
      <Panel title={t("branding")} hint={t("brandingHint")}>
        <SettingsGroup
          keys={["branding.primary_colour", "branding.header", "branding.logo_attachment_id"]}
          settings={settings}
          labels={{
            "branding.primary_colour": t("brandColour"),
            "branding.header": t("brandHeader"),
            "branding.logo_attachment_id": t("brandLogo"),
          }}
          save={save}
        />

        {/*
          Where the brand does NOT reach, stated here so nobody hunts for a
          switch that was never built. An administrator who expects the staff
          workspace to follow the brand and finds it does not will look for the
          setting that turns that on — and the honest answer is that there
          isn't one, by design.
        */}
        <p className="text-xs text-fg-muted" data-slot="brand-reach">
          {t("brandReach")}
        </p>

        <p className="text-xs text-fg-muted" data-slot="brand-contrast-note">
          {t("brandContrastNote")}
        </p>
      </Panel>

      <Panel title={t("language")} hint={t("defaultLocaleHint")}>
        <SettingsGroup
          keys={["platform.default_locale"]}
          settings={settings}
          labels={{ "platform.default_locale": t("defaultLocale") }}
          save={save}
        />
      </Panel>
    </div>
  );
}
