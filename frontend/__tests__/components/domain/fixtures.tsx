import type { ColumnDef, FilterDef } from "@/components/domain/DataTable/DataTable.types";

export interface TicketRow {
  id: string;
  reference: string;
  subject: string;
  age: number;
}

export const ROWS: TicketRow[] = [
  { id: "1", reference: "TKT-000121", subject: "Late delivery", age: 3 },
  { id: "2", reference: "TKT-000122", subject: "Damaged item", age: 7 },
  { id: "3", reference: "TKT-000123", subject: "Wrong address", age: 1 },
  { id: "4", reference: "TKT-000124", subject: "Refund request", age: 12 },
];

export const COLUMNS: ColumnDef<TicketRow>[] = [
  {
    id: "reference",
    header: "Reference",
    identity: true,
    sortable: true,
    cell: (row) => row.reference,
  },
  { id: "subject", header: "Subject", sortable: true, cell: (row) => row.subject },
  {
    id: "age",
    header: "Age",
    type: "number",
    sortable: true,
    secondary: true,
    cell: (row) => row.age,
  },
];

export const FILTERS: FilterDef[] = [
  {
    id: "priority",
    label: "Priority",
    options: [
      { value: "urgent", label: "Urgent" },
      { value: "normal", label: "Normal" },
    ],
  },
];

export const getRowId = (row: TicketRow) => row.id;
