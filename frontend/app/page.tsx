import { directionFor, resolveLocale } from "@/lib/i18n/locale";

/**
 * Placeholder page.
 *
 * Story 1.2 ships no user-facing screen — this exists so the app renders and so
 * the token layer, the self-hosted fonts and the bilingual shell are visible in
 * `pnpm dev`. It is written to the same rules as everything else: semantic
 * tokens only, logical utilities only, tabular figures on the numeric surface.
 *
 * The real screens arrive with their own stories, in components/screens/.
 */
export default function Home() {
  const locale = resolveLocale();
  const dir = directionFor(locale);

  return (
    <main className="mx-auto flex min-h-screen max-w-3xl flex-col justify-center gap-6 p-8">
      <header className="flex flex-col gap-2">
        <p className="text-sm font-medium text-fg-muted">Ragab CRM</p>
        <h1 className="text-2xl font-semibold text-fg-default">Design system floor</h1>
        <p className="max-w-prose text-md text-fg-muted">
          The token layer, the bilingual shell and the component library are in place. No screen is
          built yet.
        </p>
      </header>

      <dl className="grid grid-cols-1 gap-px overflow-hidden rounded-md border border-border-default bg-border-default sm:grid-cols-3">
        {[
          { label: "Locale", value: locale },
          { label: "Direction", value: dir },
          { label: "Typeface", value: "IBM Plex Sans" },
        ].map((item) => (
          <div key={item.label} className="flex flex-col gap-1 bg-surface-raised p-4">
            <dt className="text-xs font-medium text-fg-muted">{item.label}</dt>
            <dd className="text-md text-fg-default">{item.value}</dd>
          </div>
        ))}
      </dl>

      <p className="text-sm text-fg-subtle">
        Tabular figures, isolated from the surrounding direction:{" "}
        <span className="num">TKT-000123</span>
      </p>
    </main>
  );
}
