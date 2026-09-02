import type { ReactNode } from "react";

/**
 * The authentication routes render outside the app chrome.
 *
 * A sidebar full of destinations you cannot reach, above a sign-in form, is
 * both confusing and a small information leak about what the product contains.
 */
export default function AuthLayout({ children }: { children: ReactNode }) {
  return <div className="flex min-h-screen items-center justify-center p-6">{children}</div>;
}
