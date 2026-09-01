"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState, type FormEvent } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { AuthError, login } from "@/lib/auth/api";

/**
 * Staff sign-in.
 *
 * The password never leaves this component except in the request body, and the
 * response carries no credential back — the session arrives as an http-only
 * cookie the browser stores itself.
 */
export function SignInForm() {
  const t = useTranslations("auth.signIn");
  const router = useRouter();
  const params = useSearchParams();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setPending(true);

    try {
      await login({ email, password });

      // Back to wherever the session expired, if we know.
      router.replace(safeRedirect(params.get("redirect")));
    } catch (caught) {
      setError(messageFor(caught, t));
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="flex w-full max-w-sm flex-col gap-4" noValidate>
      <div className="flex flex-col gap-1">
        <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>
        <p className="text-sm text-fg-muted">{t("subtitle")}</p>
      </div>

      {error && (
        <FormAlert tone="error">{error}</FormAlert>
      )}

      <FormField
          label={t("email")}
          type="email"
          name="email"
          value={email}
          autoComplete="username"
          required
          onChange={(event) => setEmail(event.target.value)}
        />

      <FormField
          label={t("password")}
          type="password"
          name="password"
          value={password}
          autoComplete="current-password"
          required
          onChange={(event) => setPassword(event.target.value)}
        />

      <SubmitButton variant="primary" pending={pending} pendingLabel={t("submitting")}>
        {t("submit")}
      </SubmitButton>

      <Link href="/forgot-password" className="text-sm text-accent-text hover:underline">
        {t("forgot")}
      </Link>
    </form>
  );
}

/**
 * Only ever returns a path inside this application.
 *
 * `?redirect=` is attacker-controllable — it is put there by a link anyone can
 * send. A bare `startsWith("/")` check is not enough: `//evil.example` is
 * protocol-relative, starts with a slash, and navigates straight off-site. That
 * is an open redirect, and on a sign-in page it is a phishing primitive.
 */
function safeRedirect(target: string | null): string {
  if (!target) return "/";
  if (!target.startsWith("/")) return "/";
  if (target.startsWith("//")) return "/";
  // `/\evil.example` is treated as protocol-relative by some browsers.
  if (target.startsWith("/\\")) return "/";

  return target;
}

/**
 * Maps a failure to a message the reader can act on.
 *
 * Everything unexpected collapses to one generic line rather than surfacing a
 * server string: an error body is the easiest place to leak an internal detail.
 */
function messageFor(caught: unknown, t: (key: string) => string): string {
  if (!(caught instanceof AuthError)) return t("errorGeneric");

  if (caught.problem?.code === "security.invalid_credentials") return t("errorInvalid");
  if (caught.status === 429) return t("errorLocked");

  return t("errorGeneric");
}
