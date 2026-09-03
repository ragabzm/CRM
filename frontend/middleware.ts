import type { NextRequest } from "next/server";
import { NextResponse } from "next/server";

/**
 * Publishes the requested path to the server components below it.
 *
 * A layout cannot see the URL it is rendering — App Router gives it params, not
 * a pathname — and the session gate needs one so it can put the reader back
 * where they were aiming after they sign in. A request header is the supported
 * way to hand it down.
 *
 * Deliberately NOT doing the auth check here: middleware runs on the edge with
 * no way to confirm a session beyond "a cookie is present", and a cookie that
 * exists is not a session that is valid. Guessing here and verifying later
 * would mean two answers that can disagree. `requireSession()` asks the API,
 * which is the only thing that actually knows.
 */
export function middleware(request: NextRequest) {
  const headers = new Headers(request.headers);

  headers.set("x-pathname", request.nextUrl.pathname);

  return NextResponse.next({ request: { headers } });
}

export const config = {
  // Everything except Next's own assets. The gate itself decides which routes
  // are protected; this only supplies the path.
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
};
