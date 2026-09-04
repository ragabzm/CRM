"use client";

import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";

import { listDepartments, listStaff } from "@/lib/api/admin";
import { useFormat } from "@/lib/format/useFormat";

/**
 * How big the thing being configured actually is.
 *
 * The console header said "Administration" and nothing else. The mockup puts
 * the shape of the organisation right under it — how many people, how many
 * teams — because every setting below is a decision about that, and an
 * administrator arriving cold has no other way to know whether they are
 * looking at a desk of four or forty.
 *
 * Renders nothing until it knows. A line that appears saying "0 people" and
 * then corrects itself is worse than a line that arrives a moment late.
 */
export function AdminContextLine() {
  const t = useTranslations("admin");
  const format = useFormat();

  const [shape, setShape] = useState<{ people: number; departments: number } | null>(null);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      try {
        const [people, departments] = await Promise.all([listStaff(), listDepartments()]);

        if (!cancelled) {
          setShape({ people: people.length, departments: departments.length });
        }
      } catch {
        // A missing context line is a smaller problem than a console that
        // will not render.
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  if (shape === null) return null;

  return (
    <p className="text-sm text-fg-muted" data-slot="admin-context">
      {t("context", {
        people: format.number(shape.people),
        departments: format.number(shape.departments),
      })}
    </p>
  );
}
