"use client";

import Link from "next/link";
import { useTranslations } from "next-intl";
import { useState, type FormEvent } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { ApiError } from "@/lib/api/errors";
import { registerPortalAccount } from "@/lib/portal/api";

export interface PortalRegisterScreenProps {
  onRegistered: () => void;
}

/**
 * Opening an account.
 *
 * Four fields. Every extra one is a reason somebody abandons a form they only
 * opened because something had already gone wrong — and the business already
 * knows most of these people from their email.
 */
export function PortalRegisterScreen({ onRegistered }: PortalRegisterScreenProps) {
  const t = useTranslations("portal.auth");

  const [form, setForm] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    preferred_locale: "en" as "en" | "ar",
  });
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function set(field: keyof typeof form, value: string) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  async function submit(event: FormEvent) {
    event.preventDefault();

    setPending(true);
    setError(null);

    try {
      await registerPortalAccount(form);
      onRegistered();
    } catch (caught) {
      /*
       * The server's own words where it gave any — "an account already exists
       * for this address" tells somebody exactly what to do next, and a generic
       * failure leaves them retrying the same thing.
       */
      setError(
        caught instanceof ApiError
          ? (caught.problem?.detail ?? t("genericError"))
          : t("genericError"),
      );
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4" data-slot="portal-register">
      <h1 className="text-xl font-semibold text-fg-default">{t("register")}</h1>
      <p className="text-sm text-fg-muted">{t("registerHint")}</p>

      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      <FormField
        label={t("name")}
        name="name"
        autoComplete="name"
        value={form.name}
        onChange={(event) => set("name", event.target.value)}
      />

      <FormField
        label={t("email")}
        name="email"
        type="email"
        autoComplete="email"
        value={form.email}
        onChange={(event) => set("email", event.target.value)}
      />

      <FormField
        label={t("password")}
        name="password"
        type="password"
        autoComplete="new-password"
        hint={t("passwordHint")}
        value={form.password}
        onChange={(event) => set("password", event.target.value)}
      />

      <FormField
        label={t("passwordConfirm")}
        name="password_confirmation"
        type="password"
        autoComplete="new-password"
        value={form.password_confirmation}
        onChange={(event) => set("password_confirmation", event.target.value)}
      />

      {/* Asked, not inferred. The language somebody picks for themselves beats
          whatever their browser happens to advertise. */}
      <SegmentedFilter
        label={t("language")}
        value={form.preferred_locale}
        options={[
          { value: "en", label: "English" },
          { value: "ar", label: "العربية" },
        ]}
        onChange={(value) => set("preferred_locale", value)}
      />

      <div>
        <SubmitButton variant="primary" pending={pending}>
          {t("register")}
        </SubmitButton>
      </div>

      <p className="text-sm text-fg-muted">
        {t("haveAccount")}{" "}
        <Link href="/portal/sign-in" className="underline">
          {t("signIn")}
        </Link>
      </p>
    </form>
  );
}
