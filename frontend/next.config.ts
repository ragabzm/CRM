import createNextIntlPlugin from "next-intl/plugin";
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Emits a self-contained server bundle so the runtime image does not need
  // node_modules or a package manager. See the Dockerfile's runner stage.
  output: "standalone",

  reactStrictMode: true,

  // Next 16 no longer runs ESLint during `next build`; `pnpm lint` is its own
  // CI job. Type errors do still fail the build, which is what keeps
  // `strict: true` load-bearing rather than advisory.
  typescript: { ignoreBuildErrors: false },
};

/*
 * Registers i18n/request.ts. Without the plugin, next-intl cannot resolve
 * messages during a server render and every route fails with "Couldn't find
 * next-intl config file".
 */
const withNextIntl = createNextIntlPlugin("./i18n/request.ts");

export default withNextIntl(nextConfig);
