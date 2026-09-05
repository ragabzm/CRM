import { HelpCentreScreen } from "@/components/screens/portal/HelpCentreScreen";
import { PortalShell } from "@/components/shell/portal/PortalShell";

/** The customer help centre. No account needed. */
export default function Page() {
  return (
    <PortalShell signedIn={false}>
      <HelpCentreScreen />
    </PortalShell>
  );
}
