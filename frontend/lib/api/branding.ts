"use client";

import { request } from "@/lib/api/request";

/**
 * The three branding values, and there is no fourth.
 *
 * A logo, a primary colour and a header line. No application name, no separate
 * sign-in logo, no palette, no typography or spacing control, no preview
 * workflow, no theme builder and no custom CSS — there is no field for any of
 * them on either side of this boundary.
 *
 * PRESENTATION ONLY. Nothing here can add or remove a control, change a field
 * set, or alter what a customer may do. A brand that could would be a
 * permission model wearing a colour picker.
 */
export interface BrandingValues {
  logo_url: string | null;
  primary_colour: string | null;
  header: string | null;
}

export const NO_BRANDING: BrandingValues = {
  logo_url: null,
  primary_colour: null,
  header: null,
};

export async function fetchBranding(fetchImpl: typeof fetch = fetch): Promise<BrandingValues> {
  const body = await request<{ data: BrandingValues }>("/branding", {
    method: "GET",
    fetchImpl,
  });

  return body.data;
}
