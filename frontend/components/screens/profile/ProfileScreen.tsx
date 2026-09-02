"use client";

import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";

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

  useEffect(() => {
    getProfile()
      .then((profile) => {
        setUser(profile);
        setName(profile.name);
        setLocale(profile.preferred_locale === "ar" ? "ar" : "en");
      })
      .catch(() => undefined);
  }, []);

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

      <form onSubmit={saveProfile} className="flex max-w-sm flex-col gap-4">
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
