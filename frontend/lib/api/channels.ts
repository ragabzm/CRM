"use client";

import { request } from "@/lib/api/request";

/** One configured way in. */
export interface ChannelAccount {
  id: string;
  channel: string;
  name: string;
  is_active: boolean;
  department_id: number | null;
  department_name: string | null;
  inbound_last_7_days: number;
}

export async function listChannelAccounts(
  fetchImpl: typeof fetch = fetch,
): Promise<ChannelAccount[]> {
  const body = await request<{ data: ChannelAccount[] }>("/admin/channels", { fetchImpl });

  return body.data;
}

export async function updateChannelAccount(
  id: string,
  changes: { is_active?: boolean; department_id?: number | null },
  fetchImpl: typeof fetch = fetch,
): Promise<Pick<ChannelAccount, "id" | "channel" | "name" | "is_active" | "department_id">> {
  return request(`/admin/channels/${id}`, {
    method: "PATCH",
    body: JSON.stringify(changes),
    fetchImpl,
  });
}
