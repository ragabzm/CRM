"use client";

import { useTranslations } from "next-intl";

import { EmptyState } from "@/components/domain/EmptyState/EmptyState";

import { PortalGate } from "./PortalGate";

/**
 * Where a customer's requests will be listed.
 *
 * Story 6.2 builds the list. This exists so the shell's first destination is a
 * real page rather than a 404 — the failure that made `/tickets` a dead link in
 * the staff navigation for weeks.
 */
export function PortalRequestsPage() {
  const t = useTranslations("portal");

  return (
    <PortalGate>
      {() => <EmptyState headline={t("nav.requests")} description={t("auth.registerHint")} />}
    </PortalGate>
  );
}
