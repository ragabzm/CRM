"use client";

import { useTranslations } from "next-intl";

import { Panel } from "./Panel";
import { SettingRow } from "@/components/domain/SettingRow/SettingRow";
import { SettingsGroup } from "./SettingsGroup";
import { useSettings } from "./useSettings";

/** The four priorities, in severity order — the same order the API publishes. */
const PRIORITIES = ["low", "normal", "high", "urgent"] as const;

/**
 * Response and resolution targets, working hours, holidays, at-risk threshold.
 *
 * The matrix is a table because it IS one: a priority's response target only
 * means anything next to the other three, and stacking them into eight
 * independent fields loses the comparison the administrator is actually making.
 */
export function ServiceLevelsSection() {
  const t = useTranslations("admin.serviceLevels");
  const { settings, save } = useSettings();

  return (
    <div className="flex flex-col gap-6">
      <Panel title={t("targets")} hint={t("targetsHint")}>
        <p className="text-sm text-fg-muted">{t("notYetEnforced")}</p>

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

                return (
                  <tr key={priority} className="border-b border-border-subtle">
                    <th scope="row" className="p-2 text-start font-medium text-fg-default">
                      {priority}
                    </th>
                    <td className="p-2">
                      {response && (
                        <SettingRow
                          setting={response}
                          label={t("response")}
                          onSave={(value) => save(response.key, value)}
                        />
                      )}
                    </td>
                    <td className="p-2">
                      {resolution && (
                        <SettingRow
                          setting={resolution}
                          label={t("resolution")}
                          onSave={(value) => save(resolution.key, value)}
                        />
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
        <SettingsGroup keys={["sla.working_hours"]} settings={settings} save={save} />
      </Panel>

      <Panel title={t("holidays")} hint={t("holidaysHint")}>
        <SettingsGroup keys={["sla.holidays"]} settings={settings} save={save} />
      </Panel>

      <Panel title={t("atRisk")} hint={t("atRiskHint")}>
        <SettingsGroup keys={["sla.at_risk_threshold_percent"]} settings={settings} save={save} />
      </Panel>
    </div>
  );
}
