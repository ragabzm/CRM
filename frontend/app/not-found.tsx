import { getTranslations } from "next-intl/server";
import Link from "next/link";

/**
 * The application's own 404.
 *
 * Without this file Next renders its built-in one: a black panel reading
 * "404 | This page could not be found." in hard-coded English, dropped into the
 * middle of the app — the wrong colours, the wrong typography, and the wrong
 * language for half the people using this product.
 *
 * It sits at the root rather than inside `(app)`, so an unknown address is
 * answered whether or not anyone is signed in, and renders without chrome:
 * a sidebar around a page that does not exist suggests the reader is somewhere
 * they are not.
 */
export default async function NotFound() {
  const t = await getTranslations("notFound");

  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
      <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>

      <p className="max-w-prose text-sm text-fg-muted">{t("body")}</p>

      {/* A way out. A dead end with no route back is how a wrong link turns
          into a closed tab. */}
      <Link
        href="/"
        className="rounded-md border border-border-default px-4 py-2 text-sm font-medium text-fg-default hover:bg-surface-hover"
      >
        {t("home")}
      </Link>
    </main>
  );
}
