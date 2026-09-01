import { NextResponse } from "next/server";

/**
 * Sign-out stub.
 *
 * TODO(Story 2.1): clear the Sanctum session and redirect. It exists now so the
 * user menu posts to a real endpoint rather than to a dead href — the chrome is
 * finished, the auth behind it is not.
 */
export async function POST(): Promise<NextResponse> {
  return new NextResponse(null, { status: 204 });
}
