"use client";

import { useTranslations } from "next-intl";
import { useState, type FormEvent } from "react";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import { FormField } from "@/components/domain/FormField/FormField";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { ApiError } from "@/lib/api/errors";
import {
  saveArticleTranslation,
  type ArticleDetail,
  type ArticleLocale,
} from "@/lib/api/knowledge";

export interface ArticleEditorProps {
  article: ArticleDetail;
  onSaved: (article: ArticleDetail) => void;
}

/**
 * Writing one article in one language at a time.
 *
 * A language switcher rather than two panes side by side, because the two
 * versions are independent documents: an Arabic-only article is complete, and
 * a layout that puts an empty English box next to it says otherwise every time
 * the author looks at it.
 *
 * The body is sent as HTML and sanitised by the SERVER. Nothing here tries to
 * clean it — a second, weaker copy of that rule in the browser would disagree
 * with the real one, and the disagreement would show up as an author whose
 * work was silently altered or refused for no visible reason.
 */
export function ArticleEditor({ article, onSaved }: ArticleEditorProps) {
  const t = useTranslations("admin.knowledge");

  const [locale, setLocale] = useState<ArticleLocale>(article.default_locale);
  const [drafts, setDrafts] = useState<Record<string, { title: string; body: string }>>(() =>
    Object.fromEntries(
      article.translations.map((translation) => [
        translation.locale,
        { title: translation.title, body: translation.body },
      ]),
    ),
  );
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const current = drafts[locale] ?? { title: "", body: "" };
  const written = article.available_locales.includes(locale);

  function set(field: "title" | "body", value: string) {
    setDrafts((all) => ({ ...all, [locale]: { ...current, [field]: value } }));
  }

  async function save(event: FormEvent) {
    event.preventDefault();

    setPending(true);
    setError(null);

    try {
      onSaved(await saveArticleTranslation(article.id, locale, current));
    } catch (caught) {
      /*
       * The server's own words. "Everything in it was removed by the content
       * filter" tells an author exactly what happened to the block of markup
       * they pasted; a generic failure leaves them pasting it again.
       */
      setError(
        caught instanceof ApiError ? (caught.problem?.detail ?? t("saveFailed")) : t("saveFailed"),
      );
    } finally {
      setPending(false);
    }
  }

  return (
    <form
      onSubmit={(event) => void save(event)}
      className="flex flex-col gap-4"
      data-slot="article-editor"
    >
      <SegmentedFilter
        label={t("language")}
        value={locale}
        options={[
          {
            value: "en",
            label:
              t("locale.en") +
              (article.available_locales.includes("en") ? "" : ` · ${t("notWritten")}`),
          },
          {
            value: "ar",
            label:
              t("locale.ar") +
              (article.available_locales.includes("ar") ? "" : ` · ${t("notWritten")}`),
          },
        ]}
        onChange={(value) => setLocale(value === "ar" ? "ar" : "en")}
      />

      {!written && (
        // Not an error. An article that exists in one language is finished, not
        // broken, and this says which one is missing without implying it must
        // be filled in.
        <p className="text-sm text-fg-muted" data-slot="locale-not-written">
          {t("notWrittenHint", { locale: t(`locale.${locale}`) })}
        </p>
      )}

      {error !== null && <FormAlert tone="error">{error}</FormAlert>}

      <FormField
        label={t("title_field")}
        name={`title-${locale}`}
        value={current.title}
        onChange={(event) => set("title", event.target.value)}
      />

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-fg-default">{t("body")}</span>
        <textarea
          name={`body-${locale}`}
          rows={14}
          /*
           * `dir="auto"`: the browser picks direction from the first strong
           * character, which is the only thing that gets an Arabic body inside
           * English chrome — and the reverse — right without being told.
           */
          dir="auto"
          value={current.body}
          onChange={(event) => set("body", event.target.value)}
          className="rounded-md border border-border-default bg-surface-default p-3 font-mono text-sm text-fg-default"
        />
        <span className="text-xs text-fg-muted">{t("bodyHint")}</span>
      </label>

      <div>
        <SubmitButton pending={pending} pendingLabel={t("saving")}>
          {t("save")}
        </SubmitButton>
      </div>
    </form>
  );
}
