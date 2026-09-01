import { Suspense } from "react";

import { ResetPasswordForm } from "@/components/screens/auth/ResetPasswordForm";

export default function Page() {
  return (
    <Suspense>
      <ResetPasswordForm />
    </Suspense>
  );
}
