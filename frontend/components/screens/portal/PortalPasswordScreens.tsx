"use client";

import Link from "next/link";
import { useTranslations } from "next-intl";
import { useState, type FormEvent } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { ApiError } from "@/lib/api/errors";
import { portalForgotPassword, portalResetPassword } from "@/lib/portal/api";

/**
 * Asking for a reset link.
 *
 * The confirmation is deliberately vague — "if that address has an account" —
 * because a page that said "sent" for a known address and "no such account" for
 * an unknown one would be a way to discover who is a customer of this business.
 */
export function PortalForgotPasswordScreen() {
  const t = useTranslations("portal.auth");

  const [email, setEmail] = useState("");
  const [pending, setPending] = useState(false);
  const [sent, setSent] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault();
    setPending(true);

    try {
      await portalForgotPassword(email);
    } catch {
      // Deliberately swallowed: the outcome shown is the same either way, and
      // a visible failure would leak that this address was special.
    } finally {
      setPending(false);
      setSent(true);
    }
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4" data-slot="portal-forgot">
      <h1 className="text-xl font-semibold text-fg-default">{t("forgotTitle")}</h1>
      <p className="text-sm text-fg-muted">{t("forgotHint")}</p>

      {sent && <FormAlert tone="success">{t("forgotSent")}</FormAlert>}

      <FormField
        label={t("email")}
        name="email"
        type="email"
        autoComplete="email"
        value={email}
        onChange={(event) => setEmail(event.target.value)}
      />

      <div>
        <SubmitButton variant="primary" pending={pending}>
          {t("send")}
        </SubmitButton>
      </div>

      <p className="text-sm text-fg-muted">
        <Link href="/portal/sign-in" className="underline">
          {t("signIn")}
        </Link>
      </p>
    </form>
  );
}

export interface PortalResetPasswordScreenProps {
  token: string;
  email: string;
}

/** Spending the link. */
export function PortalResetPasswordScreen({ token, email }: PortalResetPasswordScreenProps) {
  const t = useTranslations("portal.auth");

  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [pending, setPending] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(event: FormEvent) {
    event.preventDefault();

    setPending(true);
    setError(null);

    try {
      await portalResetPassword({
        token,
        email,
        password,
        password_confirmation: confirmation,
      });

      setDone(true);
    } catch (caught) {
      /*
       * 422 here means the link is expired, already spent, or forged. One
       * message for all three, matching the server — telling them apart would
       * confirm to somebody holding a stale link that it was once real.
       */
      setError(
        caught instanceof ApiError && caught.status === 422 ? t("resetExpired") : t("genericError"),
      );
    } finally {
      setPending(false);
    }
  }

  if (done) {
    return (
      <div className="flex flex-col gap-4" data-slot="portal-reset-done">
        <FormAlert tone="success">{t("resetDone")}</FormAlert>

        <Link href="/portal/sign-in" className="text-sm underline">
          {t("signIn")}
        </Link>
      </div>
    );
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4" data-slot="portal-reset">
      <h1 className="text-xl font-semibold text-fg-default">{t("resetTitle")}</h1>

      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      <FormField
        label={t("newPassword")}
        name="password"
        type="password"
        autoComplete="new-password"
        hint={t("passwordHint")}
        value={password}
        onChange={(event) => setPassword(event.target.value)}
      />

      <FormField
        label={t("passwordConfirm")}
        name="password_confirmation"
        type="password"
        autoComplete="new-password"
        value={confirmation}
        onChange={(event) => setConfirmation(event.target.value)}
      />

      <div>
        <SubmitButton variant="primary" pending={pending}>
          {t("reset")}
        </SubmitButton>
      </div>
    </form>
  );
}
