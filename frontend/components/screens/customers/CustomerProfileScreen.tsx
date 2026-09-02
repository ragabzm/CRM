"use client";

import { Mail, Phone } from "lucide-react";
import { useTranslations } from "next-intl";
import { useCallback, useEffect, useState } from "react";

import { BidiValue } from "@/components/domain/BidiValue/BidiValue";
import { CustomerFormDialog } from "@/components/domain/CustomerFormDialog/CustomerFormDialog";
import { ActionBar } from "@/components/domain/ActionBar/ActionBar";
import { AttachmentsLane } from "@/components/domain/AttachmentsLane/AttachmentsLane";
import { CustomerTimeline } from "@/components/domain/CustomerTimeline/CustomerTimeline";
import { NotesLane } from "@/components/domain/NotesLane/NotesLane";
import { DestructiveConfirm } from "@/components/domain/DestructiveConfirm/DestructiveConfirm";
import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ForbiddenState } from "@/components/domain/ForbiddenState/ForbiddenState";
import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { getCustomer, setCustomerState, type Customer } from "@/lib/api/customers";
import { ApiError } from "@/lib/api/request";
import { useCurrentUser } from "@/lib/auth/useCurrentUser";
import { useFormat } from "@/lib/format/useFormat";

export interface CustomerProfileScreenProps {
  customerId: string;
  departments: Array<{ id: number; name: string }>;
  onOpenCustomer: (id: string) => void;
  /** Opens a ticket from the interaction timeline. */
  onOpenTicket: (ticketId: string) => void;
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="flex flex-col gap-3 rounded-lg border border-border-default bg-surface-base p-5">
      <h2 className="text-base font-semibold text-fg-default">{title}</h2>
      {children}
    </section>
  );
}

/**
 * One customer.
 *
 * Resolves whatever the state. A deactivated customer is absent from search so
 * they do not clutter today's work, but a link in a two-year-old ticket must
 * still open the person it refers to — a 404 there reads as data loss.
 */
export function CustomerProfileScreen({
  customerId,
  departments,
  onOpenCustomer,
  onOpenTicket,
}: CustomerProfileScreenProps) {
  const t = useTranslations("customers");
  const format = useFormat();
  const currentUser = useCurrentUser();

  const [customer, setCustomer] = useState<Customer | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [missing, setMissing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState(false);
  const [confirmingDeactivate, setConfirmingDeactivate] = useState(false);

  const load = useCallback(() => {
    getCustomer(customerId)
      .then((found) => {
        setCustomer(found);
        setForbidden(false);
        setMissing(false);
      })
      .catch((caught: unknown) => {
        if (caught instanceof ApiError && caught.status === 403) {
          setForbidden(true);

          return;
        }

        if (caught instanceof ApiError && caught.status === 404) {
          setMissing(true);

          return;
        }

        setError(t("loadError"));
      });
  }, [customerId, t]);

  useEffect(load, [load]);

  if (forbidden) {
    return <ForbiddenState headline={t("forbidden.title")} description={t("forbidden.body")} />;
  }

  if (missing) {
    return <EmptyState headline={t("profile.notFound")} />;
  }

  if (!customer) {
    return error ? <FormAlert tone="error">{error}</FormAlert> : null;
  }

  const inactive = customer.state === "inactive";

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex flex-col gap-1">
          <h1 className="text-xl font-semibold text-fg-default">{customer.full_name}</h1>
          <div className="flex flex-wrap items-center gap-2 text-sm text-fg-muted">
            {/* A Latin run inside possibly-Arabic prose. */}
            <BidiValue className="rounded-full border border-border-default px-2 py-0.5">
              {customer.reference}
            </BidiValue>
            <span
              data-state={customer.state}
              className="rounded-full border border-border-default px-2 py-0.5"
            >
              {t(`state.${customer.state}`)}
            </span>
            {inactive && customer.deactivated_at && (
              <span>
                {t("profile.deactivatedOn", { date: format.date(customer.deactivated_at) })}
              </span>
            )}
          </div>
        </div>

        <ActionBar
          actions={[
            { id: "edit", label: t("actions.edit"), onSelect: () => setEditing(true) },
            inactive
              ? {
                  id: "reactivate",
                  label: t("actions.reactivate"),
                  primary: true,
                  onSelect: () => {
                    void setCustomerState(customer.id, "active").then(setCustomer);
                  },
                }
              : {
                  id: "deactivate",
                  label: t("actions.deactivate"),
                  destructive: true,
                  onSelect: () => setConfirmingDeactivate(true),
                },
          ]}
        />
      </header>

      <Section title={t("profile.contactDetails")}>
        <ul className="flex flex-col gap-2">
          {customer.identifiers.map((identifier) => {
            const Icon = identifier.kind === "email" ? Mail : Phone;

            return (
              <li
                key={identifier.id ?? identifier.value}
                className="flex items-center gap-3 text-sm"
              >
                <Icon aria-hidden="true" className="size-4 shrink-0 text-fg-muted" />
                <BidiValue>{identifier.value}</BidiValue>
                {identifier.is_primary && (
                  <span className="rounded-full border border-border-default px-2 py-0.5 text-xs text-fg-muted">
                    {t("form.primary")}
                  </span>
                )}
              </li>
            );
          })}
        </ul>

        {customer.preferred_channel && (
          <p className="text-xs text-fg-muted">
            {t("form.preferredChannel")}:{" "}
            {t(`form.kind${customer.preferred_channel === "email" ? "Email" : "Phone"}`)}
          </p>
        )}
      </Section>

      <Section title={t("department.label")}>
        <p className="text-sm text-fg-default">{customer.department.name}</p>
        <p className="text-xs text-fg-muted">{t("department.note")}</p>
      </Section>

      <Section title={t("profile.notes")}>
        <p className="whitespace-pre-wrap text-sm text-fg-default">
          {customer.notes && customer.notes.trim() !== ""
            ? customer.notes
            : t("profile.notesEmpty")}
        </p>
      </Section>

      <div className="rounded-lg border border-border-default bg-surface-base p-5">
        <NotesLane
          customerId={customer.id}
          currentUserId={currentUser.id === "" ? null : currentUser.id}
          // Offered, never enforced here: the server decides the same way
          // whatever this component chooses to render.
          canModerate={
            currentUser.roles.includes("supervisor") || currentUser.roles.includes("administrator")
          }
        />
      </div>

      <div className="rounded-lg border border-border-default bg-surface-base p-5">
        <AttachmentsLane ownerType="customer" ownerId={customer.id} />
      </div>
      <div className="rounded-lg border border-border-default bg-surface-base p-5">
        <CustomerTimeline
          customerId={customer.id}
          onOpenTicket={onOpenTicket}
        />
      </div>

      <CustomerFormDialog
        open={editing}
        onOpenChange={setEditing}
        customer={customer}
        departments={departments}
        onSaved={setCustomer}
        onOpenExisting={onOpenCustomer}
      />

      <DestructiveConfirm
        open={confirmingDeactivate}
        onOpenChange={setConfirmingDeactivate}
        // Names the person AND says what survives. "Are you sure?" would leave
        // the agent guessing whether this destroys the customer's history.
        consequence={t("profile.confirmDeactivate", { name: customer.full_name })}
        confirmLabel={t("actions.deactivate")}
        onConfirm={() => {
          void setCustomerState(customer.id, "inactive").then((updated) => {
            setCustomer(updated);
            setConfirmingDeactivate(false);
          });
        }}
      />
    </div>
  );
}
