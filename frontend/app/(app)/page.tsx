import { Suspense } from "react";

import { AgentHomePage } from "@/components/screens/home/AgentHomePage";

/**
 * Where a signed-in agent lands.
 *
 * It used to be a static welcome panel. An agent opening the application wants
 * to know how much is waiting and what to do first, not to be greeted.
 * Chrome and the session gate come from the group layout.
 */
export default function Page() {
  return (
    /*
      `useSearchParams` reads `?tab=`, and without a boundary the whole route
      opts out of static rendering — the build says so rather than failing
      quietly.
    */
    <Suspense fallback={null}>
      <AgentHomePage />
    </Suspense>
  );
}
