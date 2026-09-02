import { getTranslations } from "next-intl/server";

/**
 * Departments and users.
 *
 * Both are already managed by the API delivered in the users-and-roles story.
 * The screens are not in the tree yet, so this section names them and says so
 * rather than offering a link that 404s.
 */
export default async function OrganisationPage() {
  const t = await getTranslations("admin.organisation");

  const panels = ["departments", "users"] as const;

  return (
    <div className="flex flex-col gap-6">
      {panels.map((key) => (
        <section
          key={key}
          className="flex flex-col gap-2 rounded-lg border border-border-default bg-surface-base p-5"
        >
          <h2 className="text-base font-semibold text-fg-default">{t(key)}</h2>
          <p className="text-sm text-fg-muted">{t(`${key}Body`)}</p>
          {/* No link yet. The API for both exists; the screens do not, and a
              link to a 404 is worse than an honest sentence. */}
          <p className="text-sm text-fg-subtle">{t("screensPending")}</p>
        </section>
      ))}
    </div>
  );
}
