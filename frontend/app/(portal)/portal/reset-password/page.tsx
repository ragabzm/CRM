import { Suspense } from "react";

import { PortalAuthPage } from "@/components/screens/portal/PortalAuthPage";

/**
 * Suspense because the screen reads the token and address out of the URL, which
 * opts its subtree into client-side rendering.
 */
export default function Page() {
  return (
    <Suspense>
      <PortalAuthPage screen="reset" />
    </Suspense>
  );
}
