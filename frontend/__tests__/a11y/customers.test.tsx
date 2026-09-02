import { describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/customers",
}));

import { render } from "@testing-library/react";

import { withIntl } from "@/__tests__/helpers/intl";
import { ActionBar } from "@/components/domain/ActionBar/ActionBar";
import { DuplicateOffer } from "@/components/domain/DuplicateOffer/DuplicateOffer";
import { SegmentedFilter } from "@/components/domain/SegmentedFilter/SegmentedFilter";
import type { DuplicateMatch } from "@/lib/api/customers";
import type { Locale } from "@/lib/i18n/locale";

import { axe } from "./axe";

const DIRECTIONS: Array<{ dir: "ltr" | "rtl"; locale: Locale }> = [
  { dir: "ltr", locale: "en" },
  { dir: "rtl", locale: "ar" },
];

function renderIn(ui: React.ReactElement, dir: "ltr" | "rtl", locale: Locale) {
  const host = document.createElement("div");
  host.setAttribute("dir", dir);
  host.setAttribute("lang", locale);
  document.body.appendChild(host);

  return render(withIntl(ui, locale), { container: host });
}

const MATCHES: DuplicateMatch[] = [
  {
    customer_id: "01BBBBBBBBBBBBBBBBBBBBBBBB",
    reference: "C-9XQ4TR2M",
    full_name: "Hana Yousef",
    state: "inactive",
    matched_value: "hana@example.test",
    matched_kind: "email",
  },
];

describe.each(DIRECTIONS)("customer surfaces · dir=$dir", ({ dir, locale }) => {
  it("the duplicate offer has no WCAG 2.1 AA violations", async () => {
    const { container } = renderIn(
      <DuplicateOffer matches={MATCHES} onOpenExisting={vi.fn()} onCreateAnyway={vi.fn()} />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the segmented filter has no violations", async () => {
    const { container } = renderIn(
      <SegmentedFilter
        label="State"
        value="active"
        options={[
          { value: "active", label: "Active" },
          { value: "inactive", label: "Inactive" },
        ]}
        onChange={vi.fn()}
      />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the action bar has no violations", async () => {
    const { container } = renderIn(
      <ActionBar
        actions={[
          { id: "edit", label: "Edit", onSelect: vi.fn() },
          { id: "deactivate", label: "Deactivate", destructive: true, onSelect: vi.fn() },
        ]}
      />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });
});
