"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";

import { DataTable } from "@/components/domain/DataTable/DataTable";
import type { ColumnDef } from "@/components/domain/DataTable/DataTable.types";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { RowActions } from "@/components/domain/RowActions/RowActions";
import { Button } from "@/components/ui/button";
import type { Category } from "@/lib/api/admin";

export interface CategoryListProps {
  categories: Category[];
  onRename: (category: Category) => void;
  onDelete: (category: Category) => void;
  /** Renders the "add" affordance. Omit it for a read-only list. */
  onAdd?: () => void;
}

/** The flat category table. */
export function CategoryList({ categories, onRename, onDelete, onAdd }: CategoryListProps) {
  const t = useTranslations("admin.ticketing");
  const [search, setSearch] = useState("");

  const visible = search.trim()
    ? categories.filter((category) =>
        `${category.name.en} ${category.name.ar}`
          .toLowerCase()
          .includes(search.trim().toLowerCase()),
      )
    : categories;

  const columns: ColumnDef<Category>[] = [
    {
      id: "name",
      header: t("columnName"),
      identity: true,
      cell: (category) => <span>{category.name.en}</span>,
    },
    {
      id: "name_ar",
      header: t("columnNameAr"),
      secondary: true,
      cell: (category) => <span dir="rtl">{category.name.ar}</span>,
    },
    {
      id: "actions",
      header: t("columnActions"),
      type: "action",
      cell: (category) => (
        <RowActions
          rowLabel={category.name.en}
          actions={[
            { id: "rename", label: t("rename"), onSelect: () => onRename(category) },
            {
              id: "delete",
              label: t("delete"),
              onSelect: () => onDelete(category),
              separated: true,
              destructive: true,
            },
          ]}
        />
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      {onAdd && (
        <Button className="w-fit" onClick={onAdd}>
          {t("addCategory")}
        </Button>
      )}

      <DataTable
        columns={columns}
        rows={visible}
        getRowId={(category) => String(category.id)}
        caption={t("categoriesCaption")}
        search={search}
        onSearchChange={setSearch}
        emptyState={<EmptyState headline={t("noCategories")} description={t("noCategoriesBody")} />}
      />
    </div>
  );
}
