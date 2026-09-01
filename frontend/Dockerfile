# syntax=docker/dockerfile:1

# Node 24 LTS, as the story requires.
FROM node:24-alpine AS base
RUN corepack enable
WORKDIR /app

# --- dependencies -----------------------------------------------------------
# Separate layer so editing application code does not reinstall the world.
FROM base AS deps
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile

# --- build ------------------------------------------------------------------
FROM base AS builder
COPY --from=deps /app/node_modules ./node_modules
COPY . .

# The version guard runs in the image too, not only in CI: an image built from a
# stale lockfile must not ship.
RUN node scripts/check-next-version.mjs \
    && pnpm run build

# --- runtime ----------------------------------------------------------------
FROM base AS runner

ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    PORT=3000 \
    HOSTNAME=0.0.0.0

RUN addgroup -g 1001 -S nodejs && adduser -S nextjs -u 1001

# `output: "standalone"` produces server.js plus exactly the node_modules it
# needs; static/ and public/ are not included in it and are copied separately.
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static
COPY --from=builder --chown=nextjs:nodejs /app/public ./public

USER nextjs
EXPOSE 3000

CMD ["node", "server.js"]
