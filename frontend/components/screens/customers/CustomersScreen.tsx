"use client";

import { useTranslations } from "next-intl";
import { useCallback, useEffect, useState } from "react";

import { BidiValue } from "@/components/domain/BidiValue/BidiValue";
import { CustomerFormDialog } from "@/components/domain/CustomerFormDialog/CustomerFormDialog";
import { DataTable } from "@/components/domain/DataTable/DataTable";
import type { ColumnDef } from "@/components/domain/DataTable/DataTable.types";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { ActionBar } from "@/components/domain/ActionBar/ActionBar";
import { RowActions } from "@/components/domain/RowActions/RowActions";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import { listCustomers, type Customer, type CustomerFilters } from "@/lib/api/customers";
import { ApiError } from "@/lib/api/request";
import { useFormat } from "@/lib/format/useFormat";

export interface CustomersScreenProps {
  departments: Array<{ id: number; name: string }>;
  onOpenCustomer: (id: string) => void;
}

/** The first identifier of a kind, which is what a list column can show. */
function firstOf(customer: Customer, kind: "email" | "phone"): string | null {
  const primary = customer.identifiers.find((i) => i.kind === kind && i.is_primary);

  return (primary ?? customer.identifiers.find((i) => i.kind === kind))?.value ?? null;
}

/**
 * Finding a customer.
 *
 * One search box across name, email, phone and reference rather than a "search
 * by" dropdown: an agent with a caller on the line has one fact to hand and
 * should not have to tell the product which kind of fact it is.
 */
export function CustomersScreen({ departments, onOpenCustomer }: CustomersScreenProps) {
  const t = useTranslations("customers");
  const format = useFormat();

  const [filters, setFilters] = useState<CustomerFilters>({ state: "active", page: 1 });
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [lastPage, setLastPage] = useState(1);
  const [forbidden, setForbidden] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [composing, setComposing] = useState(false);

  const load = useCallback(() => {
    let cancelled = false;

    listCustomers(filters)
      .then((page) => {
        if (cancelled) return;
        setCustomers(page.data);
        setLastPage(page.meta.last_page);
        setForbidden(false);
        setError(null);
      })
      .catch((caught: unknown) => {
        if (cancelled) return;

        if (caught instanceof ApiError && caught.status === 403) {
          setForbidden(true);

          return;
        }

        setError(t("loadError"));
      });

    return () => {
      cancelled = true;
    };
  }, [filters, t]);

  useEffect(load, [load]);

  if (forbidden) {
    return <ForbiddenState headline={t("forbidden.title")} description={t("forbidden.body")} />;
  }

  const columns: ColumnDef<Customer>[] = [
    {
      id: "full_name",
      header: t("columns.name"),
      identity: true,
      cell: (customer) => <span>{customer.full_name}</span>,
    },
    {
      id: "reference",
      header: t("columns.reference"),
      cell: (customer) => <BidiValue>{customer.reference}</BidiValue>,
    },
    {
      id: "department",
      header: t("columns.department"),
      secondary: true,
      cell: (customer) => <span>{customer.department.name}</span>,
    },
    {
      id: "email",
      header: t("columns.email"),
      secondary: true,
      cell: (customer) => {
        const value = firstOf(customer, "email");

        return value ? <BidiValue>{value}</BidiValue> : null;
      },
    },
    {
      id: "phone",
      header: t("columns.phone"),
      secondary: true,
      cell: (customer) => {
        const value = firstOf(customer, "phone");

        return value ? <BidiValue>{value}</BidiValue> : null;
      },
    },
    {
      id: "state",
      header: t("columns.state"),
      type: "status",
      cell: (customer) => <span>{t(`state.${customer.state}`)}</span>,
    },
    {
      id: "updated_at",
      header: t("columns.updatedAt"),
      type: "date",
      secondary: true,
      cell: (customer) =>
        customer.updated_at ? <span>{format.date(customer.updated_at)}</span> : null,
    },
    {
      id: "actions",
      header: "",
      type: "action",
      cell: (customer) => (
        <RowActions
          rowLabel={customer.full_name}
          actions={[
            { id: "open", label: t("actions.open"), onSelect: () => onOpenCustomer(customer.id) },
          ]}
        />
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>
        <ActionBar
          actions={[
            {
              id: "create",
              label: t("actions.create"),
              primary: true,
              onSelect: () => setComposing(true),
            },
          ]}
        />
      </header>

      <div className="flex flex-wrap items-end gap-3" data-slot="customer-filters">
        <label className="flex flex-col gap-1 text-sm font-medium text-fg-default">
          {t("department.label")}
          <select
            value={filters.department_id ?? ""}
            onChange={(event) =>
              setFilters((current) => {
                // The key is REMOVED rather than set to undefined: with
                // exactOptionalPropertyTypes an explicit undefined is a
                // different thing from an absent filter, and the query builder
                // would serialise it.
                const rest = { ...current };
                delete rest.department_id;

                return event.target.value === ""
                  ? { ...rest, page: 1 }
                  : { ...rest, department_id: Number(event.target.value), page: 1 };
              })
            }
            className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
          >
            <option value="">{t("department.any")}</option>
            {departments.map((department) => (
              <option key={department.id} value={department.id}>
                {department.name}
              </option>
            ))}
          </select>
        </label>

        <SegmentedFilter
          label={t("columns.state")}
          value={filters.state ?? "active"}
          options={[
            { value: "active", label: t("state.active") },
            { value: "inactive", label: t("state.inactive") },
            { value: "all", label: t("state.all") },
          ]}
          onChange={(state) => setFilters((current) => ({ ...current, state, page: 1 }))}
        />
      </div>

      {/* Grouping, never a boundary — said out loud so nobody assumes the
          filter is doing security work. */}
      <p className="text-xs text-fg-muted">{t("department.note")}</p>

      {error && <FormAlert tone="error">{error}</FormAlert>}

      <DataTable
        columns={columns}
        rows={customers}
        getRowId={(customer) => customer.id}
        caption={t("title")}
        search={filters.q ?? ""}
        onSearchChange={(q) => setFilters((current) => ({ ...current, q, page: 1 }))}
        page={filters.page ?? 1}
        pageCount={lastPage}
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        emptyState={<EmptyState headline={t("empty.title")} description={t("empty.body")} />}
      />

      <CustomerFormDialog
        open={composing}
        onOpenChange={setComposing}
        departments={departments}
        onSaved={() => load()}
        onOpenExisting={onOpenCustomer}
      />
    </div>
  );
}
