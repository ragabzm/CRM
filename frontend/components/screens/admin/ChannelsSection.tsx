"use client";

import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";

import { DestructiveConfirm } from "@/components/domain/DestructiveConfirm/DestructiveConfirm";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { RowSkeleton } from "@/components/domain/RowSkeleton/RowSkeleton";
import { listChannelAccounts, updateChannelAccount, type ChannelAccount } from "@/lib/api/channels";
import { listDepartments, type Department } from "@/lib/api/admin";

/**
 * Every way a customer can reach the desk, and the team each one lands in.
 *
 * Its own section rather than a panel inside Organisation, which was already
 * 451 lines. The thing an administrator does here is operational — switching a
 * door open or shut — and it does not belong beside the list of who works
 * where.
 *
 * Disabling asks first, and the question names the consequence in both
 * directions: what stops, and what does not. "Are you sure?" would move the
 * decision to the reader without giving them anything to decide with.
 */
export function ChannelsSection() {
  const t = useTranslations("channels.admin");

  const [accounts, setAccounts] = useState<ChannelAccount[] | null>(null);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [confirming, setConfirming] = useState<ChannelAccount | null>(null);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      try {
        const [rows, teams] = await Promise.all([listChannelAccounts(), listDepartments()]);
        if (cancelled) return;
        setAccounts(rows);
        setDepartments(teams);
      } catch {
        if (cancelled) return;
        // Out loud: an empty list and a silent failure look identical, and one
        // of them means "nothing is configured" while the other means "you
        // cannot see what is configured".
        setAccounts([]);
        setError(t("loadFailed"));
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [t]);

  async function apply(
    account: ChannelAccount,
    changes: Parameters<typeof updateChannelAccount>[1],
  ) {
    setBusyId(account.id);
    setError(null);

    try {
      const updated = await updateChannelAccount(account.id, changes);

      setAccounts((current) =>
        (current ?? []).map((row) =>
          row.id === account.id
            ? {
                ...row,
                is_active: updated.is_active,
                department_id: updated.department_id,
                department_name:
                  departments.find((d) => d.id === updated.department_id)?.name ?? null,
              }
            : row,
        ),
      );
    } catch {
      setError(t("saveFailed"));
    } finally {
      setBusyId(null);
    }
  }

  function toggle(account: ChannelAccount) {
    // Enabling needs no ceremony: it can only add ways in. Disabling closes one.
    if (account.is_active) {
      setConfirming(account);

      return;
    }

    void apply(account, { is_active: true });
  }

  return (
    <section className="flex flex-col gap-4" data-slot="admin-channels">
      <header className="flex flex-col gap-1">
        <h2 className="text-lg font-semibold text-fg-default">{t("title")}</h2>
        <p className="text-sm text-fg-muted">{t("hint")}</p>
      </header>

      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      {accounts === null && <RowSkeleton rows={3} label={t("loading")} />}

      {accounts !== null && accounts.length === 0 && error === null && (
        <EmptyState headline={t("empty")} />
      )}

      {accounts !== null && accounts.length > 0 && (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-start text-fg-muted">
                <th scope="col" className="p-2 text-start font-medium">
                  {t("channel")}
                </th>
                <th scope="col" className="p-2 text-start font-medium">
                  {t("name")}
                </th>
                <th scope="col" className="p-2 text-start font-medium">
                  {t("department")}
                </th>
                <th scope="col" className="p-2 text-start font-medium">
                  {t("inbound")}
                </th>
                <th scope="col" className="p-2 text-start font-medium">
                  {t("active")}
                </th>
              </tr>
            </thead>
            <tbody>
              {accounts.map((account) => (
                <tr key={account.id} className="border-t border-border-subtle">
                  <td className="p-2 text-fg-default">{account.channel}</td>
                  <td className="p-2 text-fg-default">{account.name}</td>
                  <td className="p-2">
                    <select
                      aria-label={`${t("department")} — ${account.name}`}
                      value={account.department_id ?? ""}
                      disabled={busyId === account.id}
                      onChange={(event) =>
                        void apply(account, {
                          department_id:
                            event.target.value === "" ? null : Number(event.target.value),
                        })
                      }
                      className="h-11 rounded-md border border-border-default bg-surface-default px-2 text-sm"
                    >
                      <option value="">{t("noDepartment")}</option>
                      {departments.map((department) => (
                        <option key={department.id} value={department.id}>
                          {department.name}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td className="p-2 tabular-nums text-fg-muted">{account.inbound_last_7_days}</td>
                  <td className="p-2">
                    <input
                      type="checkbox"
                      aria-label={`${t("active")} — ${account.name}`}
                      checked={account.is_active}
                      disabled={busyId === account.id}
                      onChange={() => toggle(account)}
                      className="size-5"
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <DestructiveConfirm
        open={confirming !== null}
        onOpenChange={(open) => {
          if (!open) setConfirming(null);
        }}
        consequence={t("disableBody")}
        confirmLabel={t("disableConfirm")}
        busy={busyId !== null}
        onConfirm={() => {
          const account = confirming;
          setConfirming(null);
          if (account !== null) void apply(account, { is_active: false });
        }}
      />
    </section>
  );
}
