"use client";

import Link from "next/link";
import { useTranslations } from "next-intl";
import { useState, type FormEvent } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { ApiError } from "@/lib/api/errors";
import { portalSignIn } from "@/lib/portal/api";

export interface PortalSignInScreenProps {
  onSignedIn: () => void;
}

/**
 * Where a customer gets back in.
 *
 * One failure message for a wrong address and a wrong password, matching the
 * server: telling them apart would turn this form into a way to discover which
 * addresses have accounts.
 */
export function PortalSignInScreen({ onSignedIn }: PortalSignInScreenProps) {
  const t = useTranslations("portal.auth");

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(event: FormEvent) {
    event.preventDefault();

    setPending(true);
    setError(null);

    try {
      await portalSignIn({ email, password });
      onSignedIn();
    } catch (caught) {
      setError(
        caught instanceof ApiError && caught.status === 401 ? t("error") : t("genericError"),
      );
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-4" data-slot="portal-sign-in">
      <h1 className="text-xl font-semibold text-fg-default">{t("signIn")}</h1>

      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      <FormField
        label={t("email")}
        name="email"
        type="email"
        autoComplete="email"
        value={email}
        onChange={(event) => setEmail(event.target.value)}
      />

      <FormField
        label={t("password")}
        name="password"
        type="password"
        autoComplete="current-password"
        value={password}
        onChange={(event) => setPassword(event.target.value)}
      />

      <div>
        <SubmitButton variant="primary" pending={pending}>
          {t("signIn")}
        </SubmitButton>
      </div>

      <p className="flex flex-wrap gap-3 text-sm text-fg-muted">
        <Link href="/portal/forgot-password" className="underline">
          {t("forgot")}
        </Link>

        <span>
          {t("noAccount")}{" "}
          <Link href="/portal/register" className="underline">
            {t("register")}
          </Link>
        </span>
      </p>
    </form>
  );
}
