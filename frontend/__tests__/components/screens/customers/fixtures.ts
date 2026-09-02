import type { Customer, DuplicateMatch } from "@/lib/api/customers";

export const DEPARTMENTS = [
  { id: 1, name: "Billing" },
  { id: 2, name: "Support" },
];

export function customer(overrides: Partial<Customer> = {}): Customer {
  return {
    id: "01AAAAAAAAAAAAAAAAAAAAAAAA",
    reference: "C-3F7KQ2XH",
    full_name: "Hana Yousef",
    department: { id: 1, name: "Billing" },
    state: "active",
    preferred_channel: "email",
    identifiers: [
      { id: "01I1", kind: "email", value: "hana@example.test", is_primary: true },
      { id: "01I2", kind: "phone", value: "+44 20 7946 0958" },
    ],
    notes: null,
    updated_at: "2026-09-02T09:15:00+00:00",
    deactivated_at: null,
    ...overrides,
  };
}

export function match(overrides: Partial<DuplicateMatch> = {}): DuplicateMatch {
  return {
    customer_id: "01BBBBBBBBBBBBBBBBBBBBBBBB",
    reference: "C-9XQ4TR2M",
    full_name: "Hana Yousef",
    state: "active",
    matched_value: "hana@example.test",
    matched_kind: "email",
    ...overrides,
  };
}

export function page(rows: Customer[]) {
  return {
    data: rows,
    meta: { page: 1, per_page: 25, total: rows.length, last_page: 1 },
  };
}
