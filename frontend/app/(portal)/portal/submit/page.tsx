import { PortalShell } from "@/components/shell/portal/PortalShell";
import { PublicRequestFormScreen } from "@/components/screens/portal/PublicRequestFormScreen";

/**
 * The public form.
 *
 * In the `(portal)` group with the other pages nobody has signed in to, and
 * `signedIn={false}` for the same reason those have it: links to pages that
 * would bounce a stranger back here are not navigation.
 */
export default function Page() {
  return (
    <PortalShell signedIn={false}>
      <PublicRequestFormScreen />
    </PortalShell>
  );
}
