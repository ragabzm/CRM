"use client";

import { useTranslations } from "next-intl";
import { useCallback, useEffect, useState } from "react";

import { ActionBar } from "@/components/domain/ActionBar/ActionBar";
import { DataTable } from "@/components/domain/DataTable/DataTable";
import type { ColumnDef } from "@/components/domain/DataTable/DataTable.types";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { RowActions } from "@/components/domain/RowActions/RowActions";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import {
  createDepartment,
  createStaff,
  deactivateDepartment,
  deactivateStaff,
  listDepartments,
  listStaff,
  updateStaff,
  type Department,
  type StaffUser,
} from "@/lib/api/admin";
import { ApiError } from "@/lib/api/errors";

import { Panel } from "./Panel";

/**
 * Departments and staff accounts.
 *
 * Both APIs shipped with the users-and-roles story and were marked done. No
 * screen ever called them, so this page said "managed through the API today"
 * and meant it literally: adding a colleague was a curl command, and the
 * sentence promised a screen from a story that had already finished.
 *
 * Neither list is long — a support desk has tens of people and a handful of
 * teams — so both are plain tables with inline actions rather than paginated
 * searchable surfaces. Adding pagination to a list of six departments would be
 * chrome around nothing.
 */
const ROLES = ["administrator", "supervisor", "agent"] as const;

export function OrganisationSection() {
  const t = useTranslations("admin.organisation");

  const [departments, setDepartments] = useState<Department[]>([]);
  const [staff, setStaff] = useState<StaffUser[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);

    try {
      const [people, teams] = await Promise.all([listStaff(), listDepartments()]);

      setStaff(people);
      setDepartments(teams);
      setError(null);
    } catch (caught) {
      /*
       * Said out loud, not swallowed. An empty table here reads as "this
       * business has no staff", which is a stranger claim than "we could not
       * load them".
       */
      setError(
        caught instanceof ApiError ? (caught.problem?.detail ?? t("loadError")) : t("loadError"),
      );
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    /*
     * Deferred by a microtask. `useEffect(load, [load])` sets state
     * synchronously in the effect body, which cascades a second render on
     * every mount — the same reason `CustomersScreen` defers its own load.
     */
    void Promise.resolve().then(load);
  }, [load]);

  const departmentName = (id: number | null): string =>
    id === null
      ? t("noDepartment")
      : (departments.find((d) => d.id === id)?.name ?? t("noDepartment"));

  return (
    <div className="flex flex-col gap-6">
      {error && (
        <FormAlert tone="error" action={{ label: t("retry"), onSelect: () => void load() }}>
          {error}
        </FormAlert>
      )}

      <Panel title={t("departments")} hint={t("departmentsBody")}>
        <DepartmentList departments={departments} onChanged={load} />
      </Panel>

      <Panel title={t("users")} hint={t("usersBody")}>
        {loading && staff.length === 0 ? (
          <p role="status" className="text-sm text-fg-muted">
            {t("loading")}
          </p>
        ) : (
          <StaffList
            staff={staff}
            departments={departments}
            departmentName={departmentName}
            onChanged={load}
          />
        )}
      </Panel>
    </div>
  );
}

function DepartmentList({
  departments,
  onChanged,
}: {
  departments: Department[];
  onChanged: () => Promise<void>;
}) {
  const t = useTranslations("admin.organisation");

  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const columns: ColumnDef<Department>[] = [
    {
      id: "name",
      header: t("columns.name"),
      identity: true,
      cell: (row) => <span dir="auto">{row.name}</span>,
    },
    {
      id: "state",
      header: t("columns.state"),
      cell: (row) => (row.is_active ? t("active") : t("inactive")),
    },
    {
      id: "actions",
      header: "",
      type: "action",
      cell: (row) => (
        <RowActions
          rowLabel={row.name}
          actions={
            row.is_active
              ? [
                  {
                    id: "deactivate",
                    label: t("deactivate"),
                    onSelect: () => {
                      void deactivateDepartment(row.id).then(onChanged);
                    },
                  },
                ]
              : []
          }
        />
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <DataTable
        columns={columns}
        rows={departments}
        getRowId={(row) => String(row.id)}
        caption={t("departments")}
      />

      <form
        className="flex flex-wrap items-end gap-3"
        onSubmit={(event) => {
          event.preventDefault();

          if (name.trim() === "") return;

          setBusy(true);
          setError(null);

          void createDepartment({ name: name.trim() })
            .then(() => {
              setName("");

              return onChanged();
            })
            .catch((caught: unknown) => {
              // The server's reason names the rule — a duplicate name, most
              // often — which is more use than "invalid".
              setError(
                caught instanceof ApiError
                  ? (caught.problem?.detail ?? t("saveError"))
                  : t("saveError"),
              );
            })
            .finally(() => setBusy(false));
        }}
      >
        <FormField
          label={t("newDepartment")}
          value={name}
          onChange={(event) => setName(event.target.value)}
        />

        <SubmitButton pending={busy}>{t("addDepartment")}</SubmitButton>
      </form>

      {error && <FormAlert tone="error">{error}</FormAlert>}
    </div>
  );
}

function StaffList({
  staff,
  departments,
  departmentName,
  onChanged,
}: {
  staff: StaffUser[];
  departments: Department[];
  departmentName: (id: number | null) => string;
  onChanged: () => Promise<void>;
}) {
  const t = useTranslations("admin.organisation");

  const [composing, setComposing] = useState(false);

  const columns: ColumnDef<StaffUser>[] = [
    {
      id: "name",
      header: t("columns.name"),
      identity: true,
      cell: (row) => <span dir="auto">{row.name}</span>,
    },
    {
      id: "email",
      header: t("columns.email"),
      cell: (row) => <span dir="ltr">{row.email}</span>,
    },
    {
      id: "role",
      header: t("columns.role"),
      cell: (row) => (row.role === null ? "—" : t(`roles.${row.role}`)),
    },
    {
      id: "department",
      header: t("columns.department"),
      cell: (row) => departmentName(row.department_id),
    },
    {
      id: "state",
      header: t("columns.state"),
      cell: (row) => (row.is_active ? t("active") : t("inactive")),
    },
    {
      id: "actions",
      header: "",
      type: "action",
      cell: (row) => (
        <RowActions
          rowLabel={row.name}
          actions={
            row.is_active
              ? [
                  {
                    id: "deactivate",
                    label: t("deactivate"),
                    onSelect: () => {
                      /*
                       * Deactivated, never deleted. Deleting would orphan the
                       * assignee and the author name on everything this person
                       * ever touched, and the history would stop saying who
                       * did the work.
                       */
                      void deactivateStaff(row.id).then(onChanged);
                    },
                  },
                ]
              : [
                  {
                    id: "reactivate",
                    label: t("reactivate"),
                    onSelect: () => {
                      void updateStaff(row.id, { is_active: true }).then(onChanged);
                    },
                  },
                ]
          }
        />
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <DataTable
        columns={columns}
        rows={staff}
        getRowId={(row) => String(row.id)}
        caption={t("users")}
      />

      {composing ? (
        <NewStaffForm
          departments={departments}
          onDone={() => {
            setComposing(false);

            void onChanged();
          }}
          onCancel={() => setComposing(false)}
        />
      ) : (
        /*
         * Through `ActionBar`, not a bare `<Button>`. A screen assembled from
         * Layer-A primitives is how two screens end up putting the same action
         * in two different places — the lint rule that refuses the import
         * exists for exactly that.
         */
        <ActionBar
          actions={[
            { id: "add", label: t("addUser"), primary: true, onSelect: () => setComposing(true) },
          ]}
        />
      )}
    </div>
  );
}

function NewStaffForm({
  departments,
  onDone,
  onCancel,
}: {
  departments: Department[];
  onDone: () => void;
  onCancel: () => void;
}) {
  const t = useTranslations("admin.organisation");

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<string>("agent");
  const [departmentId, setDepartmentId] = useState<number | "">("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  return (
    <form
      className="flex flex-col gap-3 rounded-md border border-border-default p-4"
      onSubmit={(event) => {
        event.preventDefault();

        setBusy(true);
        setError(null);

        void createStaff({
          name: name.trim(),
          email: email.trim(),
          role,
          department_id: departmentId === "" ? null : departmentId,
        })
          .then(onDone)
          .catch((caught: unknown) => {
            setError(
              caught instanceof ApiError
                ? (caught.problem?.detail ?? t("saveError"))
                : t("saveError"),
            );
          })
          .finally(() => setBusy(false));
      }}
    >
      <FormField label={t("columns.name")} value={name} onChange={(e) => setName(e.target.value)} />

      <FormField
        label={t("columns.email")}
        type="email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
      />

      <label className="flex flex-col gap-1 text-sm font-medium text-fg-default">
        {t("columns.role")}
        <select
          value={role}
          onChange={(event) => setRole(event.target.value)}
          className="max-w-sm min-h-11 rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
        >
          {ROLES.map((option) => (
            <option key={option} value={option}>
              {t(`roles.${option}`)}
            </option>
          ))}
        </select>
      </label>

      <label className="flex flex-col gap-1 text-sm font-medium text-fg-default">
        {t("columns.department")}
        <select
          value={departmentId}
          onChange={(event) =>
            setDepartmentId(event.target.value === "" ? "" : Number(event.target.value))
          }
          className="max-w-sm min-h-11 rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
        >
          <option value="">{t("noDepartment")}</option>
          {departments
            .filter((department) => department.is_active)
            .map((department) => (
              <option key={department.id} value={department.id}>
                {department.name}
              </option>
            ))}
        </select>
      </label>

      {/*
        No password field, deliberately. The account is created without a
        usable one and the person sets their own through the reset flow —
        better than an administrator inventing a password and then having to
        send it to them somehow.
      */}
      <p className="text-xs text-fg-muted">{t("passwordNote")}</p>

      {error && <FormAlert tone="error">{error}</FormAlert>}

      <div className="flex items-center gap-3">
        <SubmitButton pending={busy}>{t("addUser")}</SubmitButton>

        <ActionBar actions={[{ id: "cancel", label: t("cancel"), onSelect: onCancel }]} />
      </div>
    </form>
  );
}
