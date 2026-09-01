import { Suspense } from "react";

import { SignInForm } from "@/components/screens/auth/SignInForm";

/** Staff sign-in. */
export default function Page() {
  // useSearchParams needs a suspense boundary during static generation.
  return (
    <Suspense>
      <SignInForm />
    </Suspense>
  );
}
