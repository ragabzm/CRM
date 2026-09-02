import { redirect } from "next/navigation";

/**
 * /admin has no content of its own.
 *
 * A landing page listing the six sections would duplicate the index that is
 * already on screen, so the first section is opened instead.
 */
export default function AdminIndexPage() {
  redirect("/admin/organisation");
}
