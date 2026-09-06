import { describe, expect, it, vi, beforeEach } from "vitest";
import userEvent from "@testing-library/user-event";

import { render, screen, waitFor } from "@/__tests__/helpers/intl";
import { OrganisationSection } from "@/components/screens/admin/OrganisationSection";

/**
 * Departments and staff can be managed from the console.
 *
 * The API for both shipped with the users-and-roles story and was marked done
 * against an API test. No screen ever called it, so the section the
 * Administration button lands on said "managed through the API today" — and
 * promised a screen from a story that had already finished. An administrator
 * could not add a colleague without curl.
 */

const staff = vi.hoisted(() => ({ created: [] as unknown[], deactivated: [] as number[] }));

vi.mock("@/lib/api/admin", async () => {
  const actual = await vi.importActual<typeof import("@/lib/api/admin")>("@/lib/api/admin");

  return {
    ...actual,
    listStaff: vi.fn().mockResolvedValue([
      {
        id: 1,
        name: "Hana Support",
        email: "hana@ragab.test",
        role: "agent",
        department_id: 1,
        is_active: true,
      },
      {
        id: 2,
        name: "Former Colleague",
        email: "gone@ragab.test",
        role: "agent",
        department_id: null,
        is_active: false,
      },
    ]),
    listDepartments: vi.fn().mockResolvedValue([{ id: 1, name: "Support", is_active: true }]),
    /*
     * Branches load alongside departments and staff. Without this the whole
     * panel throws and every assertion below fails on a screen that never
     * rendered — which reads as five broken features rather than one missing
     * mock.
     */
    listBranches: vi
      .fn()
      .mockResolvedValue([{ id: 1, name: "Cairo", code: "CAI", is_active: true }]),
    createBranch: vi
      .fn()
      .mockResolvedValue({ id: 2, name: "Alexandria", code: "ALX", is_active: true }),
    setBranchActive: vi.fn().mockResolvedValue({}),
    createStaff: vi.fn().mockImplementation((input: unknown) => {
      staff.created.push(input);

      return Promise.resolve({ id: 9, ...(input as object) });
    }),
    deactivateStaff: vi.fn().mockImplementation((id: number) => {
      staff.deactivated.push(id);

      return Promise.resolve();
    }),
    createDepartment: vi.fn().mockResolvedValue({ id: 2, name: "Sales", is_active: true }),
    deactivateDepartment: vi.fn().mockResolvedValue(undefined),
    updateStaff: vi.fn().mockResolvedValue({}),
  };
});

beforeEach(() => {
  staff.created = [];
  staff.deactivated = [];
});

describe("the organisation section", () => {
  it("lists the people who work here", async () => {
    render(<OrganisationSection />);

    expect(await screen.findByText("Hana Support")).toBeInTheDocument();
    expect(screen.getByText("hana@ragab.test")).toBeInTheDocument();
  });

  it("names each person's department rather than its id", async () => {
    render(<OrganisationSection />);

    await screen.findByText("Hana Support");

    // The id would be a number only the database understands.
    expect(screen.getAllByText("Support").length).toBeGreaterThan(0);
  });

  it("adds a colleague without asking for a password", async () => {
    render(<OrganisationSection />);

    await screen.findByText("Hana Support");

    await userEvent.click(screen.getByRole("button", { name: /add a colleague/i }));

    await userEvent.type(screen.getByLabelText("Name"), "Nour Fahmy");
    await userEvent.type(screen.getByLabelText("Email"), "nour@ragab.test");

    /*
     * No password box, deliberately: the account is created without a usable
     * one and the person sets their own through the reset flow, so an
     * administrator never has to invent a password and then transmit it.
     */
    expect(screen.queryByLabelText(/password/i)).not.toBeInTheDocument();

    /*
     * The trigger is replaced by the form, so there is exactly one control
     * with this name at any moment — which is also why the two forms do not
     * both say "Add".
     */
    await userEvent.click(screen.getByRole("button", { name: /add a colleague/i }));

    await waitFor(() => expect(staff.created).toHaveLength(1));

    expect(staff.created[0]).toMatchObject({
      name: "Nour Fahmy",
      email: "nour@ragab.test",
      role: "agent",
    });
  });

  it("deactivates rather than deleting", async () => {
    render(<OrganisationSection />);

    await screen.findByText("Hana Support");

    await userEvent.click(screen.getByRole("button", { name: /Hana Support/i }));
    await userEvent.click(await screen.findByRole("menuitem", { name: "Deactivate" }));

    await waitFor(() => expect(staff.deactivated).toEqual([1]));
  });

  it("offers to bring back somebody who was deactivated", async () => {
    render(<OrganisationSection />);

    await screen.findByText("Former Colleague");

    await userEvent.click(screen.getByRole("button", { name: /Former Colleague/i }));

    /*
     * A deactivated account keeps its history — it is why deactivating is not
     * deleting — so the way back has to exist.
     */
    expect(await screen.findByRole("menuitem", { name: "Reactivate" })).toBeInTheDocument();
  });
});
