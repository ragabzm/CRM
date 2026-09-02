import { render, screen, waitFor, within } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ refresh: vi.fn(), push: vi.fn() }),
  usePathname: () => "/customers/01AAAAAAAAAAAAAAAAAAAAAAAA",
}));

import { AttachmentsLane } from "@/components/domain/AttachmentsLane/AttachmentsLane";
import type { Attachment } from "@/lib/api/attachments";

const OWNER = "01AAAAAAAAAAAAAAAAAAAAAAAA";

function attachment(overrides: Partial<Attachment> = {}): Attachment {
  return {
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
    ...overrides,
  };
}

const PENDING = attachment({
  id: "01FILE00000000000000PENDING",
  filename: "contract.pdf",
  scan_status: "pending",
  downloadable: false,
});

const FAILED = attachment({
  id: "01FILE000000000000000FAILED",
  filename: "invoice.pdf",
  scan_status: "failed",
  scan_reason: "Eicar-Test-Signature",
  downloadable: false,
});

let files: Attachment[] = [];
let uploads: RequestInit[] = [];
let uploadStatus = 201;
let listStatus = 200;

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status >= 400 ? "application/problem+json" : "application/json" },
  });
}

beforeEach(() => {
  files = [attachment(), PENDING, FAILED];
  uploads = [];
  uploadStatus = 201;
  listStatus = 200;

  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      const method = (init?.method ?? "GET").toUpperCase();

      if (url.includes("csrf-cookie")) return json({});

      if (method === "POST") {
        uploads.push(init ?? {});

        if (uploadStatus !== 201) {
          return json(
            {
              type: "x",
              title: "File type is not accepted",
              status: uploadStatus,
              code: "platform.attachment_type_not_allowed",
              detail: "Files of type text/html are not accepted here.",
            },
            uploadStatus,
          );
        }

        return json(attachment({ id: "01NEW000000000000000000000" }), 201);
      }

      if (listStatus !== 200) {
        return json({ title: "no", status: listStatus, code: "security.forbidden" }, listStatus);
      }

      return json({ data: files });
    }),
  );
});

afterEach(() => vi.unstubAllGlobals());

const renderLane = () => render(<AttachmentsLane ownerType="customer" ownerId={OWNER} />);

const rowFor = (id: string) =>
  document.querySelector(`[data-attachment-id="${id}"]`) as HTMLElement;

describe("the attachments lane", () => {
  it("shows a clean file with a download control", async () => {
    renderLane();

    await screen.findByText("receipt.png");

    const row = within(rowFor("01FILE000000000000000CLEAN"));
    const link = row.getByRole("link", { name: "Download" });

    // A plain link: the endpoint answers 302 to a short-lived signed URL, and
    // letting the browser follow it keeps the bytes out of JavaScript.
    expect(link).toHaveAttribute(
      "href",
      expect.stringContaining("/attachments/01FILE000000000000000CLEAN/download"),
    );
  });

  it("shows a pending file rather than hiding it", async () => {
    renderLane();

    await screen.findByText("contract.pdf");

    // Hiding it until the scan finishes makes an upload look like it silently
    // failed.
    const row = within(rowFor("01FILE00000000000000PENDING"));
    expect(row.getByText("Pending scan")).toBeInTheDocument();
  });

  it("disables the download for a pending file and says why", async () => {
    renderLane();
    await screen.findByText("contract.pdf");

    const row = within(rowFor("01FILE00000000000000PENDING"));
    const button = row.getByRole("button", { name: "Download" });

    expect(button).toBeDisabled();
    // A greyed-out button with no explanation is a dead end.
    expect(button).toHaveAccessibleDescription(/being checked for malware/i);
    expect(row.queryByRole("link")).toBeNull();
  });

  it("shows the reason a scan failed", async () => {
    renderLane();
    await screen.findByText("invoice.pdf");

    const row = within(rowFor("01FILE000000000000000FAILED"));

    expect(row.getByText("Scan failed")).toBeInTheDocument();
    expect(row.getByText("Eicar-Test-Signature")).toBeInTheDocument();
    expect(row.getByRole("button", { name: "Download" })).toBeDisabled();
  });

  it("never offers a failed file for download", async () => {
    renderLane();
    await screen.findByText("invoice.pdf");

    expect(within(rowFor("01FILE000000000000000FAILED")).queryByRole("link")).toBeNull();
  });

  it("never renders a file's contents", async () => {
    const { container } = renderLane();
    await screen.findByText("receipt.png");

    // Displaying uploaded content inline from a trusted origin is a stored XSS
    // that no virus scanner would flag. Not an <img>, not an <iframe>, not an
    // <object>.
    expect(container.querySelector("img")).toBeNull();
    expect(container.querySelector("iframe")).toBeNull();
    expect(container.querySelector("object")).toBeNull();
    expect(container.querySelector("embed")).toBeNull();
  });

  it("uploads a chosen file as multipart", async () => {
    renderLane();
    await screen.findByText("receipt.png");

    const file = new File(["bytes"], "new.png", { type: "image/png" });
    await userEvent.upload(screen.getByLabelText("Attach a file"), file);

    await waitFor(() => expect(uploads).toHaveLength(1));

    const body = uploads[0]!.body;
    expect(body).toBeInstanceOf(FormData);
    expect((body as FormData).get("owner_type")).toBe("customer");
    expect((body as FormData).get("owner_id")).toBe(OWNER);
  });

  it("lets the browser set the multipart content type", async () => {
    renderLane();
    await screen.findByText("receipt.png");

    await userEvent.upload(
      screen.getByLabelText("Attach a file"),
      new File(["bytes"], "new.png", { type: "image/png" }),
    );

    await waitFor(() => expect(uploads).toHaveLength(1));

    // Only the browser knows the boundary it generated; setting the header by
    // hand produces a body the server cannot parse.
    const headers = uploads[0]!.headers as Record<string, string>;
    expect(headers["Content-Type"]).toBeUndefined();
    expect(headers["Idempotency-Key"]).toBeTruthy();
  });

  it("shows the server's reason when an upload is refused", async () => {
    uploadStatus = 422;

    renderLane();
    await screen.findByText("receipt.png");

    await userEvent.upload(
      screen.getByLabelText("Attach a file"),
      new File(["<html>"], "evil.png", { type: "image/png" }),
    );

    // Names the actual type, which the frontend does not know and must not
    // invent.
    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Files of type text/html are not accepted here.",
    );
  });

  it("keeps checking while a scan is pending", async () => {
    vi.useFakeTimers();

    try {
      render(<AttachmentsLane ownerType="customer" ownerId={OWNER} pollMs={1000} />);

      await vi.waitFor(() => expect(screen.queryByText("contract.pdf")).not.toBeNull());

      const before = (globalThis.fetch as unknown as { mock: { calls: unknown[] } }).mock.calls
        .length;
      await vi.advanceTimersByTimeAsync(3000);
      const after = (globalThis.fetch as unknown as { mock: { calls: unknown[] } }).mock.calls
        .length;

      expect(after).toBeGreaterThan(before);
    } finally {
      vi.useRealTimers();
    }
  });

  it("stops checking once nothing is pending", async () => {
    files = [attachment()];

    vi.useFakeTimers();

    try {
      render(<AttachmentsLane ownerType="customer" ownerId={OWNER} pollMs={1000} />);

      await vi.waitFor(() => expect(screen.queryByText("receipt.png")).not.toBeNull());

      const before = (globalThis.fetch as unknown as { mock: { calls: unknown[] } }).mock.calls
        .length;
      await vi.advanceTimersByTimeAsync(5000);
      const after = (globalThis.fetch as unknown as { mock: { calls: unknown[] } }).mock.calls
        .length;

      // A timer that runs forever on a quiet page is a battery bug.
      expect(after).toBe(before);
    } finally {
      vi.useRealTimers();
    }
  });

  it("invites the first file rather than showing an empty box", async () => {
    files = [];

    renderLane();

    expect(await screen.findByText("No files yet")).toBeInTheDocument();
  });

  it("renders the forbidden surface when refused", async () => {
    listStatus = 403;

    renderLane();

    expect(await screen.findByText("You do not have access to these files")).toBeInTheDocument();
  });

  it("isolates the filename so Arabic prose cannot reorder it", async () => {
    render(<AttachmentsLane ownerType="customer" ownerId={OWNER} />, { locale: "ar" });

    const name = await screen.findByText("receipt.png");

    expect(name.closest("bdi")).not.toBeNull();
  });
});
