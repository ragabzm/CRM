"use client";

import { useCallback, useEffect, useMemo, useState } from "react";

import { listSettings, updateSetting, type Setting } from "@/lib/api/admin";

export interface SettingsState {
  settings: Record<string, Setting>;
  loading: boolean;
  /** Saves one key, then re-reads the resolved set. */
  save: (key: string, value: unknown) => Promise<void>;
  reload: () => Promise<void>;
}

/**
 * The console's view of the settings registry.
 *
 * A save re-fetches the whole resolved set rather than patching the one key
 * locally. Settings are not independent — a validator can reject a value
 * because of another setting, and the server may store a normalised form of
 * what was sent — so trusting the local guess is how the console starts showing
 * something the server does not agree with.
 */
export function useSettings(): SettingsState {
  const [rows, setRows] = useState<Setting[]>([]);
  const [loading, setLoading] = useState(true);

  const reload = useCallback(async () => {
    try {
      setRows(await listSettings());
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const save = useCallback(
    async (key: string, value: unknown) => {
      // Deliberately NOT caught: SettingRow needs the rejection to render the
      // server's reason inline. Swallowing it here would turn a refused write
      // into a silent no-op.
      await updateSetting(key, value);
      await reload();
    },
    [reload],
  );

  const settings = useMemo(() => Object.fromEntries(rows.map((row) => [row.key, row])), [rows]);

  return { settings, loading, save, reload };
}
