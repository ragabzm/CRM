"use client";

import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";

import { SubmitButton } from "@/components/domain/SubmitButton/SubmitButton";
import { portalSignOut } from "@/lib/portal/api";

import { PortalGate } from "./PortalGate";

/**
 * What the portal knows about the person using it, and the way out.
 *
 * Deliberately thin: a name, an address, a language, and Sign out. There is no
 * role, no department and no capability here because a portal account has none
 * — showing an empty section for each would imply the product is withholding
 * something.
 */
export function PortalAccountPage() {
  const t = useTranslations("portal");
  const router = useRouter();

  return (
    <PortalGate>
      {(account) => (
        <div className="flex flex-col gap-4" data-slot="portal-account">
          <h1 className="text-xl font-semibold text-fg-default">{t("nav.account")}</h1>

          <dl className="flex flex-col gap-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-fg-muted">{t("auth.name")}</dt>
              <dd dir="auto">{account.name}</dd>
            </div>

            <div className="flex justify-between gap-3">
              <dt className="text-fg-muted">{t("auth.email")}</dt>
              {/* An address is an identifier: forced LTR so it reads the same
                  in both writing directions. */}
              <dd>
                <bdi dir="ltr" className="num">
                  {account.email}
                </bdi>
              </dd>
            </div>
          </dl>

          {/*
            A form, so signing out is a submit rather than a stray click — and
            so it reaches the server with the CSRF token the transport attaches
            to writes.
          */}
          <form
            onSubmit={(event) => {
              event.preventDefault();

              void portalSignOut()
                // Either way they end up signed out of this browser: a failed
                // request must not leave somebody stuck on a page they were
                // trying to leave.
                .catch(() => undefined)
                .finally(() => router.replace("/portal/sign-in"));
            }}
          >
            <SubmitButton variant="secondary">{t("auth.signOut")}</SubmitButton>
          </form>
        </div>
      )}
    </PortalGate>
  );
}
