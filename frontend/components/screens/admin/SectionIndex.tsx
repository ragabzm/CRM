"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";

import { cn } from "@/lib/utils";

import { listCategories, listDepartments, listStaff } from "@/lib/api/admin";

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
    <nav aria-label={t("indexLabel")} className={cn("flex flex-col gap-1", className)}>
      {ADMIN_SECTIONS.map((section) => {
        const href = SECTION_PATHS[section];
        const isCurrent = pathname === href || pathname.startsWith(`${href}/`);

        return (
          <Link
            key={section}
            href={href}
            aria-current={isCurrent ? "page" : undefined}
            className={cn(
              "rounded-md px-3 py-2 text-sm transition-colors",
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
                <span className="num ms-auto text-xs font-normal text-fg-subtle" dir="ltr">
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
function useSectionCounts(): Partial<Record<AdminSection, number>> {
  const [counts, setCounts] = useState<Partial<Record<AdminSection, number>>>({});

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      try {
        const [staff, departments, categories] = await Promise.all([
          listStaff(),
          listDepartments(),
          listCategories(),
        ]);

        if (cancelled) return;

        setCounts({
          organisation: staff.length + departments.length,
          ticketing: categories.length,
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
  }, []);

  return counts;
}
