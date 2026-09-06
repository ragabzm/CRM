"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";

import { cn } from "@/lib/utils";

import {
  listAuditEntries,
  listCategories,
  listDepartments,
  listSettings,
  listStaff,
} from "@/lib/api/admin";

import { ADMIN_SECTIONS, SECTION_PATHS, type AdminSection } from "./sections";

/**
 * The left index of the configuration console.
 *
 * Marks the current section with `aria-current="page"` as well as with weight
 * and background: a screen-reader user gets the same "you are here" the sighted
 * reader gets from the highlight.
 */
export function SectionIndex({ className }: { className?: string }) {
  const t = useTranslations("admin");
  const counts = useSectionCounts();
  // usePathname() is typed string but can be null outside a router context.
  const pathname = usePathname() ?? "";

  return (
    <nav aria-label={t("indexLabel")} className={cn("flex flex-col gap-2", className)}>
      {ADMIN_SECTIONS.map((section) => {
        const href = SECTION_PATHS[section];
        const isCurrent = pathname === href || pathname.startsWith(`${href}/`);

        return (
          <Link
            key={section}
            href={href}
            aria-current={isCurrent ? "page" : undefined}
            className={cn(
              // 44px rows, for the same reason as the shell sidebar.
              "flex min-h-11 items-center rounded-md px-3 py-2 text-sm transition-colors",
              isCurrent
                ? "bg-accent-subtle font-semibold text-accent-text"
                : "font-medium text-fg-muted hover:bg-surface-hover hover:text-fg-default",
            )}
          >
            <span className="flex items-center gap-2">
              <span>{t(`sections.${section}`)}</span>

              {/*
                The shape of each section, in the index.
                The mockup draws `/admin` as a card grid where every section
                carries a live count — "4 departments", "18 categories". This
                product redirects `/admin` straight into the first section
                instead, which is the right call (a landing page would
                duplicate this index) — but it lost the numbers with it, so an
                administrator had to open every section to find out what was
                in it.
              */}
              {counts[section] !== undefined && (
                /*
                 * NAMED, and split by kind.
                 * The first version printed one bare number — `Organisation 9`
                 * — which was six people plus three departments added
                 * together. Two different things summed into a figure nobody
                 * could take apart, next to four sections with no counter at
                 * all, so half the column read as empty.
                 */
                <span className="ms-auto text-xs font-normal text-fg-subtle">
                  {counts[section]}
                </span>
              )}
            </span>
          </Link>
        );
      })}
    </nav>
  );
}

/**
 * How much is in each section.
 *
 * Loaded once for the whole console and left undefined on failure — a missing
 * number is a smaller problem than an index that will not render, and a wrong
 * one is worse than both.
 *
 * Only the sections whose size is a fact worth knowing. "Audit log: 41,882"
 * would be a number nobody can act on, and "Platform: 4 settings" says nothing
 * about the platform.
 */
function useSectionCounts(): Partial<Record<AdminSection, string>> {
  const t = useTranslations("admin.counts");

  const [counts, setCounts] = useState<Partial<Record<AdminSection, string>>>({});

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      try {
        const [staff, departments, categories, settings, entries] = await Promise.all([
          listStaff(),
          listDepartments(),
          listCategories(),
          listSettings(),
          listAuditEntries({}),
        ]);

        if (cancelled) return;

        const slaTargets = settings.filter((s) => s.key.startsWith("sla.")).length;
        const emailSettings = settings.filter((s) => s.key.startsWith("email.")).length;
        const platformSettings = settings.filter((s) => s.key.startsWith("platform.")).length;
        const integrationSettings = settings.filter((s) =>
          s.key.startsWith("integrations."),
        ).length;

        /*
         * All of them, or none. A counter on half the list reads as "these
         * sections are empty" — which is worse than no counters at all.
         */
        setCounts({
          organisation: t("organisation", {
            people: staff.length,
            departments: departments.length,
          }),
          ticketing: t("ticketing", { categories: categories.length }),
          serviceLevels: t("serviceLevels", { targets: slaTargets }),
          email: t("email", { settings: emailSettings }),
          integrations: t("integrations", { settings: integrationSettings }),
          platform: t("platform", { settings: platformSettings }),
          auditLog: t("auditLog", { entries: entries.meta.total }),
        });
      } catch {
        /*
         * Swallowed, and the consequence is bounded: the index renders with no
         * numbers, which is exactly what it did before. Compare the new-ticket
         * form, where a swallowed failure leaves a form nobody can submit and
         * is reported out loud.
         */
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [t]);

  return counts;
}
