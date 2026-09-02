"use client";

import type { Setting } from "@/lib/api/admin";

import { SettingRow } from "@/components/domain/SettingRow/SettingRow";

export interface SettingsGroupProps {
  /** Keys to render, in the order they should appear. */
  keys: string[];
  settings: Record<string, Setting>;
  labels?: Record<string, string>;
  save: (key: string, value: unknown) => Promise<void>;
}

/**
 * Renders the named settings, skipping any the server does not declare.
 *
 * Skipping rather than erroring: a page that half-renders is more useful than
 * one that white-screens because a module was disabled and took its settings
 * with it.
 */
export function SettingsGroup({ keys, settings, labels, save }: SettingsGroupProps) {
  return (
    <div className="flex flex-col gap-6">
      {keys.map((key) => {
        const setting = settings[key];
        if (!setting) return null;

        return (
          <SettingRow
            key={key}
            setting={setting}
            {...(labels?.[key] ? { label: labels[key] } : {})}
            onSave={(value) => save(key, value)}
          />
        );
      })}
    </div>
  );
}
