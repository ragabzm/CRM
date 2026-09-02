"use client";

import { Trash2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState, type FormEvent } from "react";

import { DuplicateOffer } from "@/components/domain/DuplicateOffer/DuplicateOffer";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  createCustomer,
  previewDuplicates,
  updateCustomer,
  type ContactKind,
  type Customer,
  type CustomerInput,
  type DuplicateMatch,
} from "@/lib/api/customers";
import { ApiError } from "@/lib/api/request";
import { normaliseIdentifier } from "@/lib/customers/normalise";

export interface CustomerFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Omit to create; supply to edit. */
  customer?: Customer;
  departments: Array<{ id: number; name: string }>;
  onSaved: (customer: Customer) => void;
  onOpenExisting: (customerId: string) => void;
}

interface Row {
  kind: ContactKind;
  value: string;
  is_primary: boolean;
}

function rowsFrom(customer?: Customer): Row[] {
  if (!customer || customer.identifiers.length === 0) {
    return [{ kind: "email", value: "", is_primary: true }];
  }

  return customer.identifiers.map((identifier) => ({
    kind: identifier.kind,
    value: identifier.value,
    is_primary: identifier.is_primary ?? false,
  }));
}

/**
 * Create or edit a customer, with the duplicate offer inline.
 *
 * The offer appears between pressing Save and the record existing, which is the
 * only moment it is useful: after the fact it is a merge problem, and before
 * the fact there is nothing to compare.
 */
export function CustomerFormDialog({
  open,
  onOpenChange,
  customer,
  departments,
  onSaved,
  onOpenExisting,
}: CustomerFormDialogProps) {
  const t = useTranslations("customers.form");

  const [name, setName] = useState(customer?.full_name ?? "");
  const [departmentId, setDepartmentId] = useState<number | "">(
    customer?.department.id ?? departments[0]?.id ?? "",
  );
  const [channel, setChannel] = useState<ContactKind | "">(customer?.preferred_channel ?? "");
  const [notes, setNotes] = useState(customer?.notes ?? "");
  const [rows, setRows] = useState<Row[]>(() => rowsFrom(customer));

  const [matches, setMatches] = useState<DuplicateMatch[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  // Reset when the dialog is pointed at a different record.
  const identity = customer?.id ?? "new";
  const [syncedTo, setSyncedTo] = useState(identity);

  if (syncedTo !== identity) {
    setSyncedTo(identity);
    setName(customer?.full_name ?? "");
    setDepartmentId(customer?.department.id ?? departments[0]?.id ?? "");
    setChannel(customer?.preferred_channel ?? "");
    setNotes(customer?.notes ?? "");
    setRows(rowsFrom(customer));
    setMatches([]);
    setError(null);
  }

  const filled = rows.filter((row) => row.value.trim() !== "");

  /** Index of the first row that repeats an earlier one, or -1. */
  const repeatedAt = filled.findIndex((row, index) => {
    const key = `${row.kind}:${normaliseIdentifier(row.kind, row.value)}`;

    return filled.some(
      (other, otherIndex) =>
        otherIndex < index &&
        `${other.kind}:${normaliseIdentifier(other.kind, other.value)}` === key,
    );
  });

  function payload(confirm: boolean): CustomerInput {
    return {
      full_name: name.trim(),
      department_id: Number(departmentId),
      preferred_channel: channel === "" ? null : channel,
      notes: notes.trim() === "" ? null : notes.trim(),
      identifiers: filled.map((row) => ({
        kind: row.kind,
        value: row.value.trim(),
        is_primary: row.is_primary,
      })),
      ...(confirm ? { confirm_create_duplicate: true } : {}),
    };
  }

  async function save(confirm: boolean) {
    setBusy(true);
    setError(null);

    try {
      const saved = customer
        ? await updateCustomer(customer.id, payload(false))
        : await createCustomer(payload(confirm));

      onSaved(saved);
      onOpenChange(false);
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 409) {
        // The server's offer, which is authoritative — the client-side preview
        // is only a hint and may have run against staler data.
        const offered = (caught.problem as { matches?: DuplicateMatch[] } | null)?.matches ?? [];
        setMatches(offered);

        return;
      }

      setError(
        caught instanceof ApiError
          ? (caught.problem?.detail ?? t("genericError"))
          : t("genericError"),
      );
    } finally {
      setBusy(false);
    }
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (name.trim() === "") {
      setError(t("nameRequired"));

      return;
    }

    if (filled.length === 0) {
      setError(t("identifierRequired"));

      return;
    }

    if (repeatedAt !== -1) {
      setError(t("identifierRepeated"));

      return;
    }

    setBusy(true);

    try {
      /*
       * Ask before submitting, so the offer arrives without the agent having
       * to trigger a failure first. The server checks again regardless — this
       * is a courtesy, not the enforcement.
       */
      const found = await previewDuplicates({
        emails: filled.filter((r) => r.kind === "email").map((r) => r.value),
        phones: filled.filter((r) => r.kind === "phone").map((r) => r.value),
        ...(customer ? { exclude_customer_id: customer.id } : {}),
      });

      if (found.length > 0 && !customer) {
        setMatches(found);
        setBusy(false);

        return;
      }
    } catch {
      // A failed preview must not block the save. The server will still offer.
    }

    setBusy(false);
    await save(false);
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <form onSubmit={submit} className="flex max-h-[80vh] flex-col gap-4 overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{customer ? t("editTitle") : t("createTitle")}</DialogTitle>
            <DialogDescription>{t("identifiersHint")}</DialogDescription>
          </DialogHeader>

          <FormField
            label={t("fullName")}
            value={name}
            onChange={(event) => setName(event.target.value)}
          />

          <label className="flex flex-col gap-1 text-sm font-medium text-fg-default">
            {t("department")}
            <select
              value={departmentId}
              onChange={(event) => setDepartmentId(Number(event.target.value))}
              className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
            >
              {departments.map((department) => (
                <option key={department.id} value={department.id}>
                  {department.name}
                </option>
              ))}
            </select>
          </label>

          <fieldset className="flex flex-col gap-2">
            <legend className="text-sm font-medium text-fg-default">{t("identifiers")}</legend>

            {rows.map((row, index) => (
              <div key={index} className="flex flex-wrap items-end gap-2">
                <label className="flex flex-col gap-1 text-xs text-fg-muted">
                  {t("kindEmail")}
                  <select
                    value={row.kind}
                    aria-label={`${t("identifiers")} ${index + 1}`}
                    onChange={(event) =>
                      setRows((current) =>
                        current.map((r, i) =>
                          i === index ? { ...r, kind: event.target.value as ContactKind } : r,
                        ),
                      )
                    }
                    className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
                  >
                    <option value="email">{t("kindEmail")}</option>
                    <option value="phone">{t("kindPhone")}</option>
                  </select>
                </label>

                <FormField
                  label={row.kind === "email" ? t("kindEmail") : t("kindPhone")}
                  // LTR regardless of page direction: an email address and a
                  // phone number are Latin runs whichever language surrounds
                  // them.
                  dir="ltr"
                  value={row.value}
                  onChange={(event) =>
                    setRows((current) =>
                      current.map((r, i) =>
                        i === index ? { ...r, value: event.target.value } : r,
                      ),
                    )
                  }
                />

                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  aria-label={`${t("remove")} ${index + 1}`}
                  disabled={rows.length === 1}
                  onClick={() => setRows((current) => current.filter((_, i) => i !== index))}
                >
                  <Trash2 aria-hidden="true" className="size-4" />
                </Button>
              </div>
            ))}

            <div className="flex gap-2">
              <Button
                type="button"
                variant="secondary"
                onClick={() =>
                  setRows((current) => [
                    ...current,
                    { kind: "email", value: "", is_primary: false },
                  ])
                }
              >
                {t("addEmail")}
              </Button>
              <Button
                type="button"
                variant="secondary"
                onClick={() =>
                  setRows((current) => [
                    ...current,
                    { kind: "phone", value: "", is_primary: false },
                  ])
                }
              >
                {t("addPhone")}
              </Button>
            </div>
          </fieldset>

          <label className="flex flex-col gap-1 text-sm font-medium text-fg-default">
            {t("preferredChannel")}
            <select
              value={channel}
              onChange={(event) => setChannel(event.target.value as ContactKind | "")}
              className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
            >
              <option value="">{t("noPreference")}</option>
              <option value="email">{t("kindEmail")}</option>
              <option value="phone">{t("kindPhone")}</option>
            </select>
          </label>

          <label className="flex flex-col gap-1 text-sm font-medium text-fg-default">
            {t("notes")}
            <textarea
              rows={3}
              value={notes ?? ""}
              onChange={(event) => setNotes(event.target.value)}
              className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
            />
          </label>

          {error && <FormAlert tone="error">{error}</FormAlert>}

          <DuplicateOffer
            matches={matches}
            busy={busy}
            onOpenExisting={(match) => {
              onOpenChange(false);
              onOpenExisting(match.customer_id);
            }}
            onCreateAnyway={() => void save(true)}
          />

          <DialogFooter>
            <Button
              type="button"
              variant="secondary"
              onClick={() => onOpenChange(false)}
              disabled={busy}
            >
              {t("cancel")}
            </Button>
            <Button type="submit" disabled={busy}>
              {busy ? t("checking") : t("save")}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
