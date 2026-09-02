import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/customers/01AAAAAAAAAAAAAAAAAAAAAAAA",
}));

import { render, waitFor } from "@testing-library/react";

import { withIntl } from "@/__tests__/helpers/intl";
import { AttachmentsLane } from "@/components/domain/AttachmentsLane/AttachmentsLane";
import { NotesLane } from "@/components/domain/NotesLane/NotesLane";
import type { Attachment } from "@/lib/api/attachments";
import type { CustomerNote } from "@/lib/api/notes";
import type { Locale } from "@/lib/i18n/locale";

import { StaleVersionBanner } from "@/components/domain/StaleVersionBanner/StaleVersionBanner";

import { axe } from "./axe";

const OWNER = "01AAAAAAAAAAAAAAAAAAAAAAAA";

const NOTES: CustomerNote[] = [
  {
    id: "01NOTE0000000000000000MINE",
    customer_id: OWNER,
    author: { id: "7", name: "Hana Yousef" },
    body: "Called about the invoice.",
    created_at: "2026-09-02T09:00:00+00:00",
    updated_at: "2026-09-02T09:00:00+00:00",
    edited: true,
  },
];

const FILES: Attachment[] = [
  {
    id: "01FILE000000000000000CLEAN",
    owner_type: "customer",
    owner_id: OWNER,
    filename: "receipt.png",
    byte_size: 20480,
    mime_type: "image/png",
    uploaded_at: "2026-09-02T09:00:00+00:00",
    scan_status: "clean",
    scan_reason: null,
    downloadable: true,
  },
  {
    id: "01FILE00000000000000PENDING",
    owner_type: "customer",
    owner_id: OWNER,
    filename: "contract.pdf",
    byte_size: 1024,
    mime_type: "application/pdf",
    uploaded_at: "2026-09-02T09:00:00+00:00",
    scan_status: "pending",
    scan_reason: null,
    downloadable: false,
  },
  {
    id: "01FILE000000000000000FAILED",
    owner_type: "customer",
    owner_id: OWNER,
    filename: "invoice.pdf",
    byte_size: 2048,
    mime_type: "application/pdf",
    uploaded_at: "2026-09-02T09:00:00+00:00",
    scan_status: "failed",
    scan_reason: "Eicar-Test-Signature",
    downloadable: false,
  },
];

beforeEach(() => {
  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);
      const body = url.includes("/notes") ? { data: NOTES } : { data: FILES };

      return new Response(JSON.stringify(body), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const DIRECTIONS: Array<{ dir: "ltr" | "rtl"; locale: Locale }> = [
  { dir: "ltr", locale: "en" },
  { dir: "rtl", locale: "ar" },
];

function renderIn(ui: React.ReactElement, dir: "ltr" | "rtl", locale: Locale) {
  const host = document.createElement("div");
  host.setAttribute("dir", dir);
  host.setAttribute("lang", locale);
  document.body.appendChild(host);

  return render(withIntl(<main>{ui}</main>, locale), { container: host });
}

describe.each(DIRECTIONS)("record lanes · dir=$dir", ({ dir, locale }) => {
  it("the notes lane has no WCAG 2.1 AA violations", async () => {
    const { container, findByText } = renderIn(
      <NotesLane customerId={OWNER} currentUserId="7" canModerate />,
      dir,
      locale,
    );

    await findByText("Called about the invoice.");

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the stale version banner has no violations", async () => {
    const { container } = renderIn(<StaleVersionBanner onReload={vi.fn()} />, dir, locale);

    expect(await axe(container)).toHaveNoViolations();
  });

  it("the attachments lane has no violations in all three states", async () => {
    const { container, findByText } = renderIn(
      <AttachmentsLane ownerType="customer" ownerId={OWNER} />,
      dir,
      locale,
    );

    await findByText("receipt.png");
    // The disabled download and its explanation are the interesting part: an
    // aria-describedby pointing at nothing is the classic way this breaks.
    await waitFor(() =>
      expect(container.querySelector('[data-scan-status="failed"]')).not.toBeNull(),
    );

    expect(await axe(container)).toHaveNoViolations();
  });
});
