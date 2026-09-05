"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";
import { ApiError } from "@/lib/api/errors";
import { getHelpArticle, type HelpArticle } from "@/lib/portal/helpCentre";
import { cn, TOUCH_TARGET } from "@/lib/utils";

/**
 * One article, as a customer reads it.
 *
 * The body is HTML that was sanitised on WRITE — see `HtmlSanitiser`. It is
 * rendered here rather than escaped, which is the whole point of having
 * sanitised it: an article with a list and a link should look like one. The
 * two defences are sanitise-on-write and a server that never hands out a body
 * it did not clean; this screen is where the first of those pays off.
 *
 * A link to an article since archived lands on the not-found message, not on a
 * withdrawn body. Somebody following a two-year-old link is told the answer is
 * gone and offered a way on.
 */
export function HelpArticleScreen({ id }: { id: string }) {
  const t = useTranslations("portal.help");

  const [article, setArticle] = useState<HelpArticle | null>(null);
  const [gone, setGone] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    getHelpArticle(id)
      .then((found) => {
        if (!cancelled) setArticle(found);
      })
      .catch((caught: unknown) => {
        if (cancelled) return;

        /*
         * The server's own words. "It may have been withdrawn" tells somebody
         * what happened to a link they were given; a generic error tells them
         * the site is broken.
         */
        setGone(caught instanceof ApiError ? (caught.problem?.detail ?? t("gone")) : t("gone"));
      });

    return () => {
      cancelled = true;
    };
  }, [id, t]);

  if (gone !== null) {
    return (
      <section className="flex w-full max-w-2xl flex-col gap-4" data-slot="help-article-gone">
        <EmptyState
          headline={t("goneTitle")}
          description={gone}
          actions={
            <Link href="/portal/help" className={cn("text-sm underline", TOUCH_TARGET)}>
              {t("backToHelp")}
            </Link>
          }
        />
      </section>
    );
  }

  if (article === null) {
    return <p className="text-sm text-fg-muted">{t("loading")}</p>;
  }

  return (
    <article className="flex w-full max-w-2xl flex-col gap-4" data-slot="help-article">
      <Link href="/portal/help" className={cn("text-sm underline", TOUCH_TARGET)}>
        {t("backToHelp")}
      </Link>

      <h1 className="text-xl font-semibold text-fg-default">{article.title}</h1>

      {article.available_locales.length === 1 && (
        /*
         * Told, once, which language they are reading. Somebody who asked for
         * Arabic and is looking at English needs to know — the alternative is
         * a page that looks like the site is broken, or worse, one they assume
         * IS the Arabic version.
         */
        <p className="text-xs text-fg-muted" data-slot="served-locale">
          {t("servedLocale", { locale: t(`locale.${article.served_locale}`) })}
        </p>
      )}

      {/*
        Rendered, not escaped. The body was sanitised on write to an explicit
        allow-list — see `HtmlSanitiser` — and rendering is what that
        sanitising was for. Escaping here would show an article's markup to
        the reader instead of its formatting.
      */}
      <div
        data-slot="article-body"
        className="flex flex-col gap-3 text-sm text-fg-default [&_a]:underline [&_li]:ms-5 [&_li]:list-disc"
        dir="auto"
        dangerouslySetInnerHTML={{ __html: article.body ?? "" }}
      />
    </article>
  );
}
