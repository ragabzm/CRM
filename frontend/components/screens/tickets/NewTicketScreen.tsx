"use client";

import { useTranslations } from "next-intl";
import { useEffect, useRef, useState, type FormEvent } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { listCustomers, type Customer } from "@/lib/api/customers";
import { ApiError } from "@/lib/api/errors";
import { createTicket, type TicketPriority } from "@/lib/api/tickets";
import { ulid } from "@/lib/api/ulid";

export interface NewTicketScreenProps {
  departments: Array<{ id: number; name: string }>;
  categories: Array<{ id: number; name: string }>;
  onCreated: (ticketId: string) => void;
  /** Pre-selects the customer when opened from their profile. */
  customerId?: string;
}

const PRIORITIES: TicketPriority[] = ["low", "normal", "high", "urgent"];

/**
 * Opening a ticket.
 *
 * The idempotency key is captured ONCE on mount, not per submit. A double
 * click, a flaky connection or an impatient retry then replays the original
 * response instead of opening a second ticket for one problem — which is the
 * failure an agent creates and a supervisor has to clean up.
 */
export function NewTicketScreen({
  departments,
  categories,
  onCreated,
  customerId,
}: NewTicketScreenProps) {
  const t = useTranslations("tickets.new");
  const tPriority = useTranslations("tickets.priority");

  // One key for the life of this form.
  const idempotencyKey = useRef(ulid());

  const [subject, setSubject] = useState("");
  const [description, setDescription] = useState("");
  const [customer, setCustomer] = useState(customerId ?? "");
  const [search, setSearch] = useState("");
  const [matches, setMatches] = useState<Customer[]>([]);
  const [categoryId, setCategoryId] = useState<number | "">("");
  const [priority, setPriority] = useState<TicketPriority>("normal");
  const [departmentId, setDepartmentId] = useState<number | "">("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    const term = search.trim();

    if (term === "") return;

    let cancelled = false;

    /*
     * Debounced. Searching on every keystroke sends a request per character
     * while an agent types a name, and the answers arrive out of order — the
     * last one to land wins, which is not the last one asked for.
     */
    const timer = setTimeout(() => {
      listCustomers({ q: term, limit: 5 })
        .then((page) => {
          if (!cancelled) setMatches(page.data);
        })
        .catch(() => undefined);
    }, 250);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [search]);

  /*
   * Derived rather than cleared in the effect: an empty box shows no matches
   * because there is nothing to match, not because something reset state.
   */
  const visibleMatches = search.trim() === "" ? [] : matches;

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (subject.trim() === "") return setError(t("subjectRequired"));
    if (description.trim() === "") return setError(t("descriptionRequired"));
    if (customer === "") return setError(t("customerRequired"));

    setError(null);
    setBusy(true);

    try {
      const ticket = await createTicket(
        {
          subject: subject.trim(),
          description: description.trim(),
          customer_id: customer,
          channel: "agent",
          priority,
          ...(categoryId === "" ? {} : { category_id: categoryId }),
          ...(departmentId === "" ? {} : { department_id: departmentId }),
        },
        idempotencyKey.current,
      );

      onCreated(ticket.id);
    } catch (caught) {
      // The server's reason, which names the actual field.
      setError(caught instanceof ApiError ? (caught.problem?.detail ?? t("error")) : t("error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} className="flex max-w-2xl flex-col gap-4">
      <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>

      <FormField
        label={t("subject")}
        hint={t("subjectHint")}
        value={subject}
        maxLength={200}
        onChange={(event) => setSubject(event.target.value)}
      />

      <label className="flex flex-col gap-1 text-sm font-medium text-fg-default">
        {t("description")}
        <textarea
          rows={5}
          value={description}
          onChange={(event) => setDescription(event.target.value)}
          className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm text-fg-default"
        />
      </label>

      {customerId === undefined && (
        <div className="flex flex-col gap-2">
          <FormField
            label={t("customer")}
            placeholder={t("customerPlaceholder")}
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />

          {visibleMatches.length > 0 && (
            <ul className="flex flex-col gap-1" aria-label={t("customer")}>
              {visibleMatches.map((match) => (
                <li key={match.id}>
                  <label className="flex items-center gap-2 text-sm text-fg-default">
                    <input
                      type="radio"
                      name="customer"
                      value={match.id}
                      checked={customer === match.id}
                      onChange={() => setCustomer(match.id)}
                    />
                    <span>
                      {match.full_name} · {match.reference}
                    </span>
                  </label>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      <label className="flex flex-col gap-1 text-sm font-medium text-fg-default">
        {t("category")}
        <select
          value={categoryId}
          onChange={(event) =>
            setCategoryId(event.target.value === "" ? "" : Number(event.target.value))
          }
          className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
        >
          <option value="">{t("noCategory")}</option>
          {categories.map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </select>
      </label>

      <label className="flex flex-col gap-1 text-sm font-medium text-fg-default">
        {t("priority")}
        <select
          value={priority}
          onChange={(event) => setPriority(event.target.value as TicketPriority)}
          className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
        >
          {PRIORITIES.map((value) => (
            <option key={value} value={value}>
              {tPriority(value)}
            </option>
          ))}
        </select>
      </label>

      <label className="flex flex-col gap-1 text-sm font-medium text-fg-default">
        {t("department")}
        <select
          value={departmentId}
          onChange={(event) =>
            setDepartmentId(event.target.value === "" ? "" : Number(event.target.value))
          }
          className="rounded-md border border-border-default bg-surface-base px-3 py-2 text-sm"
        >
          <option value="">{t("noDepartment")}</option>
          {departments.map((department) => (
            <option key={department.id} value={department.id}>
              {department.name}
            </option>
          ))}
        </select>
      </label>

      {error && <FormAlert tone="error">{error}</FormAlert>}

      <SubmitButton pending={busy} className="w-fit">
        {busy ? t("submitting") : t("submit")}
      </SubmitButton>
    </form>
  );
}
