import { getTranslations } from "next-intl/server";
import type { ReactNode } from "react";

import { AdminContextLine } from "@/components/screens/admin/AdminContextLine";
import { SectionIndex } from "@/components/screens/admin/SectionIndex";

/**
 * The configuration console frame.
 *
 * The section index is a second, nested navigation inside the shell's own
 * sidebar rather than a replacement for it: an administrator configuring
 * something is still working, and taking away the route back to tickets makes
 * the console feel like a different application.
 */
export default async function AdminLayout({ children }: { children: ReactNode }) {
  const t = await getTranslations("admin");

  return (
    <>
      <div className="flex flex-col gap-6">
        <div className="flex flex-col gap-1">
          <h1 className="text-xl font-semibold text-fg-default">{t("title")}</h1>
          <AdminContextLine />
        </div>

        <div className="grid gap-8 tablet:grid-cols-[13rem_1fr]">
          <SectionIndex />
          <div className="min-w-0">{children}</div>
        </div>
      </div>
    </>
  );
}
