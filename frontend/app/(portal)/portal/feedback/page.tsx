import { Suspense } from "react";

import { FeedbackLandingScreen } from "@/components/screens/portal/FeedbackLandingScreen";
import { PortalShell } from "@/components/shell/portal/PortalShell";

/**
 * The page an emailed rating link lands on. No account needed — the signature
 * on the link is the whole authorisation, and it grants nothing but the one
 * answer it already recorded.
 */
export default function Page() {
  return (
    <PortalShell signedIn={false}>
      {/* `useSearchParams` needs one: without it the whole route opts out of
          static rendering and the build says so. */}
      <Suspense fallback={null}>
        <FeedbackLandingScreen />
      </Suspense>
    </PortalShell>
  );
}
