import { render, screen } from "@/__tests__/helpers/intl";
import userEvent from "@testing-library/user-event";
import * as React from "react";
import { describe, expect, it } from "vitest";

import { DataTable } from "@/components/domain/DataTable/DataTable";

import { COLUMNS, ROWS, getRowId } from "./fixtures";

function ColumnsHarness() {
  const [hidden, setHidden] = React.useState<string[]>([]);

  return (
    <DataTable
      caption="Tickets"
      columns={COLUMNS}
      rows={ROWS}
      getRowId={getRowId}
      hiddenColumns={hidden}
      onHiddenColumnsChange={setHidden}
    />
  );
}

describe("DataTable column visibility", () => {
  it("locks the identity column: the toggle is rendered and disabled, not absent", async () => {
    const user = userEvent.setup();
    render(<ColumnsHarness />);

    await user.click(screen.getByRole("button", { name: /columns/i }));

    const identityToggle = await screen.findByRole("checkbox", { name: /reference/i });

    // "Locked, not absent" — the control exists so the reader learns the rule.
    expect(identityToggle).toBeInTheDocument();
    expect(identityToggle).toBeDisabled();
    expect(identityToggle).toHaveAttribute("aria-disabled", "true");
  });

  it("explains why the identity column cannot be hidden", async () => {
    const user = userEvent.setup();
    render(<ColumnsHarness />);

    await user.click(screen.getByRole("button", { name: /columns/i }));

    const identityToggle = await screen.findByRole("checkbox", { name: /reference/i });
    const describedBy = identityToggle.getAttribute("aria-describedby");

    expect(describedBy).toBeTruthy();
    expect(document.getElementById(describedBy!)).toHaveTextContent(/cannot be hidden/i);
  });

  it("hides a non-identity column when toggled", async () => {
    const user = userEvent.setup();
    render(<ColumnsHarness />);

    expect(screen.getByRole("columnheader", { name: /subject/i })).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /columns/i }));
    await user.click(await screen.findByRole("checkbox", { name: /subject/i }));

    expect(screen.queryByRole("columnheader", { name: /subject/i })).not.toBeInTheDocument();
  });

  it("keeps the identity column rendered even if it is passed as hidden", () => {
    render(
      <DataTable
        caption="Tickets"
        columns={COLUMNS}
        rows={ROWS}
        getRowId={getRowId}
        hiddenColumns={["reference", "subject"]}
      />,
    );

    // The identity column survives a hostile prop; the other does not.
    expect(screen.getByRole("columnheader", { name: /reference/i })).toBeInTheDocument();
    expect(screen.queryByRole("columnheader", { name: /subject/i })).not.toBeInTheDocument();
  });
});
