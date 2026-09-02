"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";

import { cn } from "@/lib/utils";

import { ADMIN_SECTIONS, SECTION_PATHS } from "./sections";

/**
 * The left index of the configuration console.
 *
 * Marks the current section with `aria-current="page"` as well as with weight
 * and background: a screen-reader user gets the same "you are here" the sighted
 * reader gets from the highlight.
 */
export function SectionIndex({ className }: { className?: string }) {
  const t = useTranslations("admin");
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
            {t(`sections.${section}`)}
          </Link>
        );
      })}
    </nav>
  );
}
