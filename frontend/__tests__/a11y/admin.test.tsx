import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/admin/ticketing",
}));

import { render, screen } from "@testing-library/react";

import { withIntl } from "@/__tests__/helpers/intl";
import { CategoryList } from "@/components/domain/CategoryList/CategoryList";
import { DestructiveConfirm } from "@/components/domain/DestructiveConfirm/DestructiveConfirm";
import { QuickReplyEditor } from "@/components/domain/QuickReplyEditor/QuickReplyEditor";
import { QuickReplyList } from "@/components/domain/QuickReplyList/QuickReplyList";
import { RuleBlockedRefusal } from "@/components/domain/RuleBlockedRefusal/RuleBlockedRefusal";
import { EmailTestSend } from "@/components/domain/EmailTestSend/EmailTestSend";
import { MailLogTable } from "@/components/domain/MailLogTable/MailLogTable";
import { MailQuarantineTable } from "@/components/domain/MailQuarantine/MailQuarantineTable";
import { SettingRow } from "@/components/domain/SettingRow/SettingRow";
import { SectionIndex } from "@/components/screens/admin/SectionIndex";
import type { Category, QuickReply, Setting } from "@/lib/api/admin";
import type { Problem } from "@/lib/api/client";
import type { Locale } from "@/lib/i18n/locale";

import { AuditEntryDetail } from "@/components/domain/AuditEntryDetail/AuditEntryDetail";
import { AuditFilterBar } from "@/components/domain/AuditFilterBar/AuditFilterBar";

import { axe } from "./axe";

/**
 * The console's accessibility gate, in BOTH writing directions.
 *
 * A violation that appears only in Arabic — a control whose label came from a
 * direction-specific branch, a mirrored layout that loses a focus ring — is the
 * kind that ships, because most reviewers never open the Arabic build.
 */
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

const SETTINGS: Setting[] = [
  {
    key: "tickets.auto_close_hours",
    type: "int",
    value: 168,
    default: 168,
    secret: false,
    configured: false,
    summary: "Hours a resolved ticket waits before closing itself.",
    allowed_values: null,
  },
  {
    key: "platform.default_locale",
    type: "enum",
    value: "en",
    default: "en",
    secret: false,
    configured: false,
    summary: "Language for people who have not chosen one.",
    allowed_values: ["en", "ar"],
  },
  {
    key: "email.mailbox.password",
    type: "string",
    value: null,
    default: null,
    secret: true,
    configured: false,
    summary: "Mailbox password.",
    allowed_values: null,
  },
  {
    key: "sla.holidays",
    type: "json",
    value: [],
    default: [],
    secret: false,
    configured: false,
    summary: "Dates the clock does not run.",
    allowed_values: null,
  },
];

const REPLIES: QuickReply[] = [
  { id: "01A", label: { en: "Greeting", ar: "تحية" }, body: { en: "Hello", ar: "مرحبا" } },
  { id: "01B", label: { en: "Closing", ar: "خاتمة" }, body: { en: "Thanks", ar: "شكرا" } },
];

const CATEGORIES: Category[] = [{ id: 1, name: { en: "Billing", ar: "الفواتير" }, sort_order: 1 }];

const BLOCKED = {
  type: "https://ragab.example/problems/tickets/category-in-use",
  title: "Category is still in use",
  status: 409,
  detail: "Cannot delete: 7 tickets still use this category.",
  code: "tickets.category_in_use",
  count: 7,
  path: "/tickets?category=1",
} as unknown as Problem;

describe.each(DIRECTIONS)("the configuration console · dir=$dir", ({ dir, locale }) => {
  it("the section index has no WCAG 2.1 AA violations", async () => {
    const { container } = renderIn(<SectionIndex />, dir, locale);

    expect(await axe(container)).toHaveNoViolations();
  });

  it.each(SETTINGS)("a $type setting row has no violations", async (setting) => {
    // Every type, because each renders a different control and each control
    // wires its label and description differently.
    const { container } = renderIn(
      <SettingRow setting={setting} label="Setting" onSave={async () => undefined} />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the reorderable list has no violations", async () => {
    const { container } = renderIn(
      <QuickReplyList
        replies={REPLIES}
        onReorder={vi.fn()}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
        onAdd={vi.fn()}
      />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the bilingual editor has no violations", async () => {
    // Both language fields are on screen at once, each with its own dir — the
    // case most likely to produce an unlabelled control.
    const { container } = renderIn(
      <QuickReplyEditor onSubmit={async () => undefined} onCancel={vi.fn()} />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the category table has no violations", async () => {
    const { container } = renderIn(
      <CategoryList
        categories={CATEGORIES}
        onRename={vi.fn()}
        onDelete={vi.fn()}
        onAdd={vi.fn()}
      />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the destructive confirmation has no violations", async () => {
    renderIn(
      <DestructiveConfirm
        open
        onOpenChange={vi.fn()}
        consequence="This will delete the category “Billing”."
        confirmLabel="Delete"
        onConfirm={vi.fn()}
      />,
      dir,
      locale,
    );

    // The dialog portals to document.body, so the scan has to start there.
    expect(await axe(document.body)).toHaveNoViolations();
  });

  it("the audit filter bar has no violations", async () => {
    const { container } = renderIn(
      <AuditFilterBar
        filters={{}}
        onChange={vi.fn()}
        actions={["user.created", "config.changed"]}
        labelForAction={(action) => action}
      />,
      dir,
      locale,
    );

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the audit entry detail has no violations", async () => {
    renderIn(
      <AuditEntryDetail
        entry={{
          id: "01AAAAAAAAAAAAAAAAAAAAAAAA",
          occurred_at: "2026-09-02T09:15:00+00:00",
          actor: { id: "7", type: "user", label: "Hana Yousef" },
          action: "user.updated",
          target: { type: "user", id: "41" },
          source_ip: "203.0.113.7",
          request_id: null,
          before: { name: "Before" },
          after: { name: "After" },
        }}
        onOpenChange={vi.fn()}
      />,
      dir,
      locale,
    );

    expect(await axe(document.body)).toHaveNoViolations();
  });

  it("the rule-blocked refusal has no violations", async () => {
    const { container } = renderIn(<RuleBlockedRefusal problem={BLOCKED} />, dir, locale);

    expect(await axe(container)).toHaveNoViolations();
  });
});

describe.each(DIRECTIONS)("the email console · dir=$dir", ({ dir, locale }) => {
  beforeEach(() => {
    vi.stubGlobal(
      "fetch",
      vi.fn(
        async () =>
          new Response(JSON.stringify({ data: [], meta: { total: 0 } }), {
            status: 200,
            headers: { "Content-Type": "application/json" },
          }),
      ),
    );
  });

  afterEach(() => vi.unstubAllGlobals());

  it("the test-send form has no WCAG 2.1 AA violations", async () => {
    const { container } = renderIn(<EmailTestSend />, dir, locale);

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the quarantine list has no violations", async () => {
    const { container } = renderIn(<MailQuarantineTable />, dir, locale);

    // Empty is the state an administrator sees first, and the one a bare
    // table would fail on.
    await screen.findByRole("table");

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the mail log has no violations", async () => {
    const { container } = renderIn(<MailLogTable />, dir, locale);

    // Empty is the state an administrator sees first, and the one a bare
    // table would fail on.
    await screen.findByRole("table");

    expect(await axe(container)).toHaveNoViolations();
  });
});
