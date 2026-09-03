"use client";

import { useRouter, useSearchParams } from "next/navigation";

import { PortalShell } from "@/components/shell/portal/PortalShell";

import { PortalForgotPasswordScreen, PortalResetPasswordScreen } from "./PortalPasswordScreens";
import { PortalRegisterScreen } from "./PortalRegisterScreen";
import { PortalSignInScreen } from "./PortalSignInScreen";

export type PortalAuthScreen = "sign-in" | "register" | "forgot" | "reset";

/**
 * The four unauthenticated portal pages, in the portal's own shell.
 *
 * `signedIn={false}` hides the destinations: three links to pages that will
 * bounce you back here are not navigation, they are a maze.
 */
export function PortalAuthPage({ screen }: { screen: PortalAuthScreen }) {
  const router = useRouter();
  const search = useSearchParams();

  const done = () => router.push("/portal/requests");

  return (
    <PortalShell signedIn={false}>
      {screen === "sign-in" && <PortalSignInScreen onSignedIn={done} />}
      {screen === "register" && <PortalRegisterScreen onRegistered={done} />}
      {screen === "forgot" && <PortalForgotPasswordScreen />}
      {screen === "reset" && (
        <PortalResetPasswordScreen
          token={search.get("token") ?? ""}
          email={search.get("email") ?? ""}
        />
      )}
    </PortalShell>
  );
}
