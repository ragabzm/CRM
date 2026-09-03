"use client";

import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState, type FormEvent } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import {
  AuthError,
  changePassword,
  getProfile,
  updateProfile,
  type StaffUser,
} from "@/lib/auth/api";
import { LOCALE_COOKIE } from "@/lib/i18n/locale";

/** Name, language, and password — the three things a person owns about themselves. */
export function ProfileScreen() {
  const t = useTranslations("profile");
  const router = useRouter();

  const [user, setUser] = useState<StaffUser | null>(null);
  const [name, setName] = useState("");
  const [locale, setLocale] = useState<"en" | "ar">("en");
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loadFailed, setLoadFailed] = useState(false);
  const [loading, setLoading] = useState(true);

  /**
   * Loads the person's own details.
   *
   * The failure used to be swallowed with `.catch(() => undefined)`, which left
   * the form standing there with empty Name and Email fields, no spinner and no
   * message — indistinguishable from a profile that genuinely has no name. Type
   * into it and Save would overwrite the real record with whatever was on
   * screen. A form that cannot show what it is editing must say so.
   */
  const loadProfile = useCallback(() => {
    let cancelled = false;

    void Promise.resolve().then(() => {
      if (cancelled) return;

      setLoading(true);
      setLoadFailed(false);
    });

    getProfile()
      .then((profile) => {
        if (cancelled) return;

        setUser(profile);
        setName(profile.name);
        setLocale(profile.preferred_locale === "ar" ? "ar" : "en");
      })
      .catch(() => {
        if (!cancelled) setLoadFailed(true);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(loadProfile, [loadProfile]);

  async function saveProfile(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setNotice(null);

    try {
      const updated = await updateProfile({ name, preferred_locale: locale });
      setUser(updated);
      setNotice(t("saved"));

      /*
       * The preference is stored on the user AND mirrored into the cookie the
       * shell reads. Without the cookie the page would keep rendering in the old
       * direction until the next sign-in — the server is the truth, the cookie
       * is what makes the change visible now.
       */
      await fetch("/api/locale", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ locale }),
      }).catch(() => undefined);

      router.refresh();
    } catch (caught) {
      setError(caught instanceof AuthError ? (caught.problem?.detail ?? t("error")) : t("error"));
    }
  }

  return (
    <div className="flex flex-col gap-8">
      <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>

      {notice && <FormAlert tone="success">{notice}</FormAlert>}
      {error && <FormAlert tone="error">{error}</FormAlert>}

      {loadFailed && (
        <FormAlert tone="error" action={{ label: t("loadRetry"), onSelect: loadProfile }}>
          {t("loadError")}
        </FormAlert>
      )}

      {loading && (
        <p role="status" className="text-sm text-fg-muted">
          {t("loading")}
        </p>
      )}

      {/*
        Nothing to edit until the record is here.
        `disabled` on the fieldset rather than a guard on submit: an enabled
        Name field over an unloaded profile invites someone to type a
        replacement for a value they were never shown, and Save would write it.
      */}
      <form onSubmit={saveProfile} className="flex max-w-sm flex-col gap-4">
        <fieldset disabled={user === null} className="flex flex-col gap-4 border-0 p-0">
          <FormField
            label={t("name")}
            name="name"
            value={name}
            maxLength={120}
            onChange={(event) => setName(event.target.value)}
          />

          {/* Read-only: changing the sign-in address is an identity change that
            needs verification of the new address and notice to the old. */}
          <FormField
            label={t("email")}
            name="email"
            value={user?.email ?? ""}
            readOnly
            disabled
            hint={t("emailHint")}
          />

          <fieldset className="flex flex-col gap-2">
            <legend className="text-sm font-medium text-fg-default">{t("language")}</legend>
            {/* Each language named in its own language: an endonym is what the
              reader recognises, translated names are not. */}
            {(["en", "ar"] as const).map((option) => (
              <label key={option} className="flex items-center gap-2 text-sm">
                <input
                  type="radio"
                  name="preferred_locale"
                  value={option}
                  checked={locale === option}
                  onChange={() => setLocale(option)}
                />
                <span>{option === "en" ? t("languageEnglish") : t("languageArabic")}</span>
              </label>
            ))}
          </fieldset>

          <SubmitButton variant="primary">{t("save")}</SubmitButton>
        </fieldset>
      </form>

      <ChangePasswordCard />
    </div>
  );
}

function ChangePasswordCard() {
  const t = useTranslations("profile");

  const [current, setCurrent] = useState("");
  const [next, setNext] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setNotice(null);

    try {
      await changePassword({
        current_password: current,
        password: next,
        password_confirmation: confirmation,
      });

      setNotice(t("passwordSaved"));
      // Cleared immediately: there is no reason for a password to sit in
      // component state after the request that used it.
      setCurrent("");
      setNext("");
      setConfirmation("");
    } catch (caught) {
      setError(caught instanceof AuthError ? (caught.problem?.detail ?? t("error")) : t("error"));
    }
  }

  return (
    <section className="flex max-w-sm flex-col gap-4 border-t border-border-default pt-6">
      <h2 className="text-md font-semibold text-fg-default">{t("passwordHeading")}</h2>

      {notice && <FormAlert tone="success">{notice}</FormAlert>}
      {error && <FormAlert tone="error">{error}</FormAlert>}

      <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
        <FormField
          label={t("passwordCurrent")}
          type="password"
          name="current_password"
          value={current}
          autoComplete="current-password"
          required
          onChange={(event) => setCurrent(event.target.value)}
        />

        <FormField
          label={t("passwordNew")}
          type="password"
          name="password"
          value={next}
          autoComplete="new-password"
          required
          onChange={(event) => setNext(event.target.value)}
        />

        <FormField
          label={t("passwordConfirm")}
          type="password"
          name="password_confirmation"
          value={confirmation}
          autoComplete="new-password"
          required
          onChange={(event) => setConfirmation(event.target.value)}
        />

        <SubmitButton variant="secondary">{t("passwordSubmit")}</SubmitButton>
      </form>
    </section>
  );
}

export { LOCALE_COOKIE };
