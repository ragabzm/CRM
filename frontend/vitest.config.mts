import react from "@vitejs/plugin-react";
import { fileURLToPath } from "node:url";
import { defineConfig } from "vitest/config";

export default defineConfig({
  plugins: [react()],
  resolve: {
    // Mirrors the "@/*" path alias in tsconfig.json; Vitest does not read it.
    alias: {
      "@": fileURLToPath(new URL("./", import.meta.url)),
    },
  },
  test: {
    // jsdom for component tests; the Story 1.1 API-client tests are environment
    // agnostic and run fine here too.
    environment: "jsdom",
    // The lint tests drive the real ESLint API against the project config;
    // the first call in a cold worker resolves eslint-config-next and can
    // legitimately take longer than the 5s default.
    testTimeout: 20_000,
    hookTimeout: 120_000,
    setupFiles: ["./__tests__/setup.ts"],
    include: ["__tests__/**/*.test.ts", "__tests__/**/*.test.tsx"],
    coverage: {
      provider: "v8",
      include: ["lib/**", "scripts/**", "components/**", "tokens/**"],
    },
  },
});
