"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState, type FormEvent } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { AuthError, resetPassword } from "@/lib/auth/api";

/** Redeem a reset link. Token and address arrive in the query string. */
export function ResetPasswordForm() {
  const t = useTranslations("auth.reset");
  const router = useRouter();
  const params = useSearchParams();

  const token = params.get("token");
  const email = params.get("email");

  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  if (!token || !email) {
    return (
      <p role="alert" className="text-sm text-fg-muted">
        {t("linkMissing")}
      </p>
    );
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setPending(true);

    try {
      await resetPassword({
        token: token!,
        email: email!,
        password,
        password_confirmation: confirmation,
      });

      router.replace("/sign-in");
    } catch (caught) {
      const problem = caught instanceof AuthError ? caught.problem : null;

      /*
       * Expired, already used and forged all arrive as the same code, and the
       * reader needs the same next step for all three: request a new link.
       * The field errors from a policy failure are shown as-is.
       */
      setError(
        problem?.code === "security.reset_token_invalid"
          ? t("tokenInvalid")
          : (problem?.detail ?? t("tokenInvalid")),
      );
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="flex w-full max-w-sm flex-col gap-4" noValidate>
      <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>

      {error && <FormAlert tone="error">{error}</FormAlert>}

      <FormField
        label={t("password")}
        type="password"
        name="password"
        value={password}
        autoComplete="new-password"
        required
        onChange={(event) => setPassword(event.target.value)}
      />

      <FormField
        label={t("confirm")}
        type="password"
        name="password_confirmation"
        value={confirmation}
        autoComplete="new-password"
        required
        onChange={(event) => setConfirmation(event.target.value)}
      />

      <SubmitButton variant="primary" pending={pending}>
        {t("submit")}
      </SubmitButton>

      <Link href="/sign-in" className="text-sm text-accent-text hover:underline">
        {t("title")}
      </Link>
    </form>
  );
}
