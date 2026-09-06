import { ReportsPage } from "@/components/screens/reports/ReportsPage";

/**
 * The fixed report set.
 *
 * Its own route rather than a tab on Home, because the two answer different
 * questions: Home's counts are live and about right now, these are about a
 * period somebody chose. Mixing them on one surface is how a live number gets
 * read as a monthly one.
 */
export default function Page() {
  return <ReportsPage />;
}
