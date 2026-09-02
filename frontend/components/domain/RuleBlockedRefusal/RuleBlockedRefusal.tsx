"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";

import { FormAlert } from "@/components/domain/FormAlert/FormAlert";
import type { Problem } from "@/lib/api/client";

export interface RuleBlockedRefusalProps {
  /** The 409 problem document the server refused with. */
  problem: Problem | null;
}

/** Narrows the RFC 9457 extension members this refusal carries. */
function refusalDetails(problem: Problem | null): { count: number; path: string } | null {
  if (!problem) return null;

  const { count, path } = problem as Problem & { count?: unknown; path?: unknown };

  if (typeof count !== "number" || typeof path !== "string" || path === "") {
    return null;
  }

  return { count, path };
}

/**
 * A refusal that answers the reader's next question.
 *
 * "Cannot delete" is where a support request starts. "Cannot delete: 7 tickets
 * still use it — view them" is where one ends: the count says how big the
 * problem is and the link is the route to fixing it, so the rule teaches
 * instead of merely blocking.
 *
 * Falls back to the problem's own detail when the server did not supply the
 * extensions — a refusal with less information still has to render.
 */
export function RuleBlockedRefusal({ problem }: RuleBlockedRefusalProps) {
  const t = useTranslations("admin.blocked");
  const details = refusalDetails(problem);

  if (!details) {
    return problem ? <FormAlert tone="error">{problem.detail ?? problem.title}</FormAlert> : null;
  }

  return (
    <FormAlert tone="error">
      <span>{t("cannotDelete", { count: details.count })} </span>
      <Link href={details.path} className="font-medium underline underline-offset-2">
        {t("viewThem")}
      </Link>
    </FormAlert>
  );
}
