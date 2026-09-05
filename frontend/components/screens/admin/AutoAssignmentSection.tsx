"use client";

import { useLocale, useTranslations } from "next-intl";
import { useEffect, useState } from "react";

import { ActionBar } from "@/components/domain/ActionBar/ActionBar";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { RowSkeleton } from "@/components/domain/RowSkeleton/RowSkeleton";
import { ApiError } from "@/lib/api/errors";
import { listCategories, listDepartments, listStaff } from "@/lib/api/admin";
import {
  createAssignmentMapping,
  deleteAssignmentMapping,
  listAssignmentMappings,
  type AssignmentMapping,
  type MappingSource,
  type MappingTarget,
} from "@/lib/api/assignmentMappings";

/**
 * Where new tickets land — one line per rule, and no rule editor.
 *
 * There is no condition, no operator, no ordering control and no
 * enable/disable switch, and there is nowhere for one to go: the server allows
 * at most one mapping per source, so every list here is already unambiguous.
 *
 * Two things this screen must SAY rather than leave to be discovered. The
 * precedence — a category mapping beats a department one — is read from the
 * server so this file cannot drift from the rule. And a row whose target has
 * been deactivated is shown as inactive with the reason, because a mapping
 * that quietly never fires is worse than no mapping at all.
 */
export function AutoAssignmentSection() {
  const t = useTranslations("admin.autoAssignment");
  const locale = useLocale();
  const arabic = locale.startsWith("ar");

  const [mappings, setMappings] = useState<AssignmentMapping[] | null>(null);
  const [precedence, setPrecedence] = useState<MappingSource[]>([]);
  const [categories, setCategories] = useState<
    Array<{ id: number; name: { en: string; ar: string } }>
  >([]);
  const [departments, setDepartments] = useState<Array<{ id: number; name: string }>>([]);
  const [staff, setStaff] = useState<Array<{ id: number; name: string }>>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const [draft, setDraft] = useState({
    source_type: "category" as MappingSource,
    source_id: "",
    target_type: "agent" as MappingTarget,
    target_id: "",
  });

  async function reload() {
    const list = await listAssignmentMappings();
    setMappings(list.data);
    setPrecedence(list.precedence);
  }

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      try {
        const [list, cats, depts, people] = await Promise.all([
          listAssignmentMappings(),
          listCategories(),
          listDepartments(),
          listStaff(),
        ]);

        if (cancelled) return;
        setMappings(list.data);
        setPrecedence(list.precedence);
        setCategories(cats);
        setDepartments(depts);
        setStaff(people);
      } catch {
        if (cancelled) return;
        // Out loud: an empty list and a failed request look identical, and one
        // of them means "nothing is mapped yet".
        setMappings([]);
        setError(t("loadFailed"));
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [t]);

  const sourceName = (mapping: AssignmentMapping) => {
    if (mapping.source_type === "category") {
      const category = categories.find((c) => c.id === mapping.source_id);

      return category === undefined ? "—" : arabic ? category.name.ar : category.name.en;
    }

    return departments.find((d) => d.id === mapping.source_id)?.name ?? "—";
  };

  const targetName = (mapping: AssignmentMapping) =>
    mapping.target_type === "agent"
      ? (staff.find((p) => p.id === mapping.target_id)?.name ?? "—")
      : (departments.find((d) => d.id === mapping.target_id)?.name ?? "—");

  async function add() {
    if (draft.source_id === "" || draft.target_id === "") return;

    setBusy(true);
    setError(null);

    try {
      await createAssignmentMapping({
        source_type: draft.source_type,
        source_id: Number(draft.source_id),
        target_type: draft.target_type,
        target_id: Number(draft.target_id),
      });

      await reload();
      setDraft({ ...draft, source_id: "", target_id: "" });
    } catch (caught) {
      /*
       * The server's own words. "That source already has a mapping" tells an
       * administrator to edit the existing row; a generic failure leaves them
       * pressing Add again.
       */
      setError(
        caught instanceof ApiError ? (caught.problem?.detail ?? t("saveFailed")) : t("saveFailed"),
      );
    } finally {
      setBusy(false);
    }
  }

  async function remove(id: number) {
    setBusy(true);

    try {
      await deleteAssignmentMapping(id);
      await reload();
    } catch {
      setError(t("saveFailed"));
    } finally {
      setBusy(false);
    }
  }

  const sourceOptions = draft.source_type === "category" ? categories : departments;
  const targetOptions = draft.target_type === "agent" ? staff : departments;

  return (
    <section className="flex flex-col gap-4" data-slot="admin-auto-assignment">
      <header className="flex flex-col gap-1">
        <h2 className="text-lg font-semibold text-fg-default">{t("title")}</h2>
        <p className="text-sm text-fg-muted">{t("hint")}</p>

        {precedence[0] !== undefined && (
          /*
           * Stated, from the server. An administrator who has to discover
           * that a category beats a department will discover it from a ticket
           * that went somewhere they did not expect.
           */
          <p className="text-sm font-medium text-fg-default" data-slot="precedence">
            {t("precedence", {
              first: t(`source.${precedence[0]}`),
              second: t(`source.${precedence[1] ?? precedence[0]}`),
            })}
          </p>
        )}
      </header>

      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      {mappings === null && <RowSkeleton rows={3} label={t("loading")} />}

      {mappings !== null && mappings.length === 0 && error === null && (
        <EmptyState headline={t("empty")} description={t("emptyHint")} />
      )}

      {mappings !== null && mappings.length > 0 && (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-fg-muted">
                <th scope="col" className="p-2 text-start font-medium">
                  {t("columnSource")}
                </th>
                <th scope="col" className="p-2 text-start font-medium">
                  {t("columnTarget")}
                </th>
                <th scope="col" className="p-2 text-start font-medium">
                  {t("columnState")}
                </th>
                <th scope="col" className="p-2 text-start font-medium" />
              </tr>
            </thead>
            <tbody>
              {mappings.map((mapping) => (
                <tr key={mapping.id} className="border-t border-border-subtle">
                  <td className="p-2 text-fg-default">
                    <span className="text-fg-muted">{t(`source.${mapping.source_type}`)}: </span>
                    {sourceName(mapping)}
                  </td>
                  <td className="p-2 text-fg-default">
                    <span className="text-fg-muted">{t(`target.${mapping.target_type}`)}: </span>
                    {targetName(mapping)}
                  </td>
                  <td className="p-2" data-slot="mapping-state" data-active={mapping.active}>
                    {mapping.active ? (
                      <span className="text-fg-muted">{t("active")}</span>
                    ) : (
                      // The reason, not just a flag. Otherwise the row looks
                      // broken rather than explained.
                      <span className="font-medium text-state-danger">
                        {mapping.inactive_reason}
                      </span>
                    )}
                  </td>
                  <td className="p-2">
                    <ActionBar
                      actions={[
                        {
                          id: `remove-${mapping.id}`,
                          label: t("remove"),
                          destructive: true,
                          disabled: busy,
                          onSelect: () => void remove(mapping.id),
                        },
                      ]}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <div className="flex flex-wrap items-end gap-3" data-slot="mapping-editor">
        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-fg-default">{t("columnSource")}</span>
          <select
            aria-label={t("columnSource")}
            value={draft.source_type}
            onChange={(event) =>
              setDraft({
                ...draft,
                source_type: event.target.value as MappingSource,
                source_id: "",
              })
            }
            className="h-11 rounded-md border border-border-default bg-surface-default px-2 text-sm"
          >
            <option value="category">{t("source.category")}</option>
            <option value="department">{t("source.department")}</option>
          </select>
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="sr-only">{t("chooseSource")}</span>
          <select
            aria-label={t("chooseSource")}
            value={draft.source_id}
            onChange={(event) => setDraft({ ...draft, source_id: event.target.value })}
            className="h-11 rounded-md border border-border-default bg-surface-default px-2 text-sm"
          >
            <option value="">{t("choose")}</option>
            {sourceOptions.map((option) => (
              <option key={option.id} value={option.id}>
                {"name" in option && typeof option.name === "string"
                  ? option.name
                  : arabic
                    ? (option as { name: { ar: string } }).name.ar
                    : (option as { name: { en: string } }).name.en}
              </option>
            ))}
          </select>
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="font-medium text-fg-default">{t("columnTarget")}</span>
          <select
            aria-label={t("columnTarget")}
            value={draft.target_type}
            onChange={(event) =>
              setDraft({
                ...draft,
                target_type: event.target.value as MappingTarget,
                target_id: "",
              })
            }
            className="h-11 rounded-md border border-border-default bg-surface-default px-2 text-sm"
          >
            <option value="agent">{t("target.agent")}</option>
            <option value="department">{t("target.department")}</option>
          </select>
        </label>

        <label className="flex flex-col gap-1 text-sm">
          <span className="sr-only">{t("chooseTarget")}</span>
          <select
            aria-label={t("chooseTarget")}
            value={draft.target_id}
            onChange={(event) => setDraft({ ...draft, target_id: event.target.value })}
            className="h-11 rounded-md border border-border-default bg-surface-default px-2 text-sm"
          >
            <option value="">{t("choose")}</option>
            {targetOptions.map((option) => (
              <option key={option.id} value={option.id}>
                {option.name as string}
              </option>
            ))}
          </select>
        </label>

        <ActionBar
          actions={[
            {
              id: "add",
              label: t("add"),
              primary: true,
              disabled: busy || draft.source_id === "" || draft.target_id === "",
              onSelect: () => void add(),
            },
          ]}
        />
      </div>
    </section>
  );
}
