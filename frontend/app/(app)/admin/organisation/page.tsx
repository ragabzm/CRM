import { OrganisationSection } from "@/components/screens/admin/OrganisationSection";

/**
 * Departments and staff accounts.
 *
 * This page used to be two paragraphs saying both were "managed through the
 * API today" and that the screens would "arrive with the users story" — a
 * story that had already shipped. So an administrator opening the section the
 * Administration button lands on was told to use curl, by a sentence promising
 * something that already existed.
 */
export default function OrganisationPage() {
  return <OrganisationSection />;
}
