import type { ReactNode } from "react";

/**
 * The customer's half of the application.
 *
 * A route group of its own, NOT inside `(app)`. That group's layout calls
 * `requireSession()`, which asks the staff API who is signed in — a customer
 * would be bounced to the staff sign-in form before a portal page ever
 * rendered.
 *
 * It also means the two shells cannot drift into each other: a change to the
 * agent chrome has no path into this tree.
 */
export default function PortalLayout({ children }: { children: ReactNode }) {
  return children;
}
