"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";
import { useState, type FormEvent } from "react";

import { FormField } from "@/components/domain/FormField/FormField";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { forgotPassword } from "@/lib/auth/api";

/**
 * Request a reset link.
 *
 * The confirmation is the SAME whether or not the address belongs to an
 * account, and it is shown even if the request failed. Anything else turns this
 * form into an account-enumeration oracle: submit a list of addresses, read the
 * differences, learn who works here.
 */
export function ForgotPasswordForm() {
  const t = useTranslations("auth.forgot");

  const [email, setEmail] = useState("");
  const [submitted, setSubmitted] = useState(false);
  const [pending, setPending] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);

    // Deliberately no catch-and-report: the outcome must not vary with whether
    // the address exists, and the server already answers 202 either way.
    await forgotPassword({ email }).catch(() => undefined);

    setPending(false);
    setSubmitted(true);
  }

  if (submitted) {
    return (
      <div className="flex w-full max-w-sm flex-col gap-4">
        <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>
        <p role="status" className="text-sm text-fg-muted">
          {t("submitted")}
        </p>
        <Link href="/sign-in" className="text-sm text-accent-text hover:underline">
          {t("backToSignIn")}
        </Link>
      </div>
    );
  }

  return (
    <form onSubmit={onSubmit} className="flex w-full max-w-sm flex-col gap-4" noValidate>
      <div className="flex flex-col gap-1">
        <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>
        <p className="text-sm text-fg-muted">{t("intro")}</p>
      </div>

      <FormField
          label={t("email")}
          type="email"
          name="email"
          value={email}
          autoComplete="username"
          required
          onChange={(event) => setEmail(event.target.value)}
        />

      <SubmitButton variant="primary" pending={pending}>
        {t("submit")}
      </SubmitButton>

      <Link href="/sign-in" className="text-sm text-accent-text hover:underline">
        {t("backToSignIn")}
      </Link>
    </form>
  );
}
