"use client";

import { useTranslations } from "next-intl";

import { Panel } from "./Panel";
import { SettingRow } from "@/components/domain/SettingRow/SettingRow";
import { SlaTargetPreview } from "@/components/domain/SlaTargetPreview/SlaTargetPreview";
import { SettingsGroup } from "./SettingsGroup";
import { useSettings } from "./useSettings";

/** The four priorities, in severity order — the same order the API publishes. */
const PRIORITIES = ["low", "normal", "high", "urgent"] as const;

/** A stored target, in the minutes the field edits. */
function minutesOf(value: unknown): number | null {
  return typeof value === "number" && value > 0 ? Math.round(value / 60) : null;
}

/**
 * Response and resolution targets, working hours, holidays, at-risk threshold.
 *
 * The matrix is a table because it IS one: a priority's response target only
 * means anything next to the other three, and stacking them into eight
 * independent fields loses the comparison the administrator is actually making.
 */
export function ServiceLevelsSection() {
  const t = useTranslations("admin.serviceLevels");
  const tPriority = useTranslations("tickets.priority");
  const { settings, save } = useSettings();

  return (
    <div className="flex flex-col gap-6">
      <Panel title={t("targets")} hint={t("targetsHint")}>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <caption className="sr-only">{t("targetsCaption")}</caption>
            <thead>
              <tr className="border-b border-border-default text-start">
                <th scope="col" className="p-2 text-start font-medium text-fg-muted">
                  {t("priority")}
                </th>
                <th scope="col" className="p-2 text-start font-medium text-fg-muted">
                  {t("response")}
                </th>
                <th scope="col" className="p-2 text-start font-medium text-fg-muted">
                  {t("resolution")}
                </th>
              </tr>
            </thead>
            <tbody>
              {PRIORITIES.map((priority) => {
                const response = settings[`sla.response_target_seconds.${priority}`];
                const resolution = settings[`sla.resolution_target_seconds.${priority}`];

                /*
                 * What the stored seconds mean in the unit the field edits.
                 * The preview asks the server what that lands on, using the
                 * same calculator the live timers use.
                 */
                const responseMinutes = minutesOf(response?.value);
                const resolutionMinutes = minutesOf(resolution?.value);

                return (
                  <tr key={priority} className="border-b border-border-subtle">
                    <th scope="row" className="p-2 text-start font-medium text-fg-default">
                      {tPriority(priority)}
                    </th>
                    <td className="p-2">
                      {response && (
                        <SettingRow
                          setting={response}
                          /*
                           * Named for assistive technology and hidden from the
                           * screen: the column header above and the priority
                           * beside it already say what this box is. It used to
                           * print "First response" as a label AND again in the
                           * summary under every one of eight cells.
                           */
                          label={`${t("response")} · ${tPriority(priority)}`}
                          labelHidden
                          hideSummary
                          onSave={(value) => save(response.key, value)}
                        />
                      )}
                      {responseMinutes !== null && <SlaTargetPreview minutes={responseMinutes} />}
                    </td>
                    <td className="p-2">
                      {resolution && (
                        <SettingRow
                          setting={resolution}
                          label={`${t("resolution")} · ${tPriority(priority)}`}
                          labelHidden
                          hideSummary
                          onSave={(value) => save(resolution.key, value)}
                        />
                      )}
                      {resolutionMinutes !== null && (
                        <SlaTargetPreview minutes={resolutionMinutes} />
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Panel>

      <Panel title={t("workingHours")} hint={t("workingHoursHint")}>
        {/*
          Every group carries labels now. Without them `SettingRow` printed the
          raw key — `sla.working_hours`, `email.inbound.webhook_secret` —
          as the field's name: developer text, untranslated, shown to an
          administrator in both languages.
        */}
        <SettingsGroup
          keys={["sla.working_hours"]}
          labels={{ "sla.working_hours": t("workingHours") }}
          settings={settings}
          save={save}
        />
      </Panel>

      <Panel title={t("holidays")} hint={t("holidaysHint")}>
        <SettingsGroup
          keys={["sla.holidays"]}
          labels={{ "sla.holidays": t("holidays") }}
          settings={settings}
          save={save}
        />
      </Panel>

      <Panel title={t("atRisk")} hint={t("atRiskHint")}>
        <SettingsGroup
          keys={["sla.at_risk_threshold_percent"]}
          labels={{ "sla.at_risk_threshold_percent": t("atRisk") }}
          settings={settings}
          save={save}
        />
      </Panel>
    </div>
  );
}
