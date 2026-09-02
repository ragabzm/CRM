"use client";

import { useTranslations } from "next-intl";
import { useState, type FormEvent } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import type { Bilingual, Category } from "@/lib/api/admin";

export interface CategoryEditorProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Omit to create; supply to rename. */
  category?: Category;
  onSubmit: (name: Bilingual) => Promise<void>;
}

/**
 * Create or rename a category, in both languages at once.
 *
 * A dialog rather than a `prompt()`: a browser prompt takes one string, which
 * would mean renaming the English name and leaving the Arabic one describing
 * something else — a category that reads correctly for half the staff.
 */
export function CategoryEditor({ open, onOpenChange, category, onSubmit }: CategoryEditorProps) {
  const t = useTranslations("admin.ticketing");
  const tConfirm = useTranslations("admin.confirm");

  const [en, setEn] = useState(category?.name.en ?? "");
  const [ar, setAr] = useState(category?.name.ar ?? "");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  // Reset the fields whenever the dialog is pointed at a different category.
  const identity = category ? String(category.id) : "new";
  const [syncedTo, setSyncedTo] = useState(identity);

  if (syncedTo !== identity) {
    setSyncedTo(identity);
    setEn(category?.name.en ?? "");
    setAr(category?.name.ar ?? "");
    setError(null);
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!en.trim() || !ar.trim()) {
      setError(t("categoriesHint"));
      return;
    }

    setBusy(true);
    setError(null);

    try {
      await onSubmit({ en: en.trim(), ar: ar.trim() });
      onOpenChange(false);
    } catch (caught) {
      const detail =
        caught && typeof caught === "object" && "problem" in caught
          ? ((caught as { problem: { detail?: string } | null }).problem?.detail ?? null)
          : null;
      setError(detail ?? t("categoriesHint"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <form onSubmit={submit} className="flex flex-col gap-4">
          <DialogHeader>
            <DialogTitle>{category ? t("rename") : t("addCategory")}</DialogTitle>
          </DialogHeader>

          <FormField
            label={t("categoryEn")}
            dir="ltr"
            value={en}
            onChange={(event) => setEn(event.target.value)}
          />
          <FormField
            label={t("categoryAr")}
            dir="rtl"
            value={ar}
            onChange={(event) => setAr(event.target.value)}
          />

          {error && <FormAlert tone="error">{error}</FormAlert>}

          <DialogFooter>
            <Button
              type="button"
              variant="secondary"
              onClick={() => onOpenChange(false)}
              disabled={busy}
            >
              {tConfirm("cancel")}
            </Button>
            <Button type="submit" disabled={busy}>
              {category ? t("rename") : t("addCategory")}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
