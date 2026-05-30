# =============================================================================
# Statflow — Frontend image (Vue 3 / Vite SPA)
#
# Build targets:
#   dev   — Vite dev server, source bind-mounted at /app
#   prod  — static dist served by Caddy (minimal image, non-root)
# =============================================================================

# ── Development target ────────────────────────────────────────────────────────
FROM node:22-bookworm-slim AS dev

LABEL org.opencontainers.image.title="Statflow Frontend (dev)" \
      org.opencontainers.image.description="Vite development server for the Statflow Vue 3 SPA" \
      org.opencontainers.image.licenses="AGPL-3.0"

# Enable corepack so pnpm version is driven by packageManager field in package.json
RUN corepack enable

# procps provides `ps`, required by Stryker mutation testing
RUN apt-get update && apt-get install -y --no-install-recommends \
        procps \
    && rm -rf /var/lib/apt/lists/*

# Dedicated non-root user
RUN groupadd --gid 1001 statflow \
    && useradd --uid 1001 --gid statflow --shell /bin/bash --create-home statflow

WORKDIR /app

# Ownership: bind-mount will layer on top but we need the base dir owned correctly
RUN chown statflow:statflow /app

USER statflow

# Vite dev server listens on 5173 by default; expose it
EXPOSE 5173

# Source is bind-mounted; dependencies installed at first `docker compose run` or
# via an explicit `pnpm install` step. The CMD just starts the dev server.
CMD ["pnpm", "run", "dev", "--host"]

# ── Builder stage for production ──────────────────────────────────────────────
FROM node:22-bookworm-slim AS builder

RUN corepack enable

WORKDIR /app

# Copy workspace manifests first for layer-cache efficiency
# Paths are relative to the build context (repo root)
COPY --chown=node:node package.json pnpm-workspace.yaml ./
COPY --chown=node:node pnpm-lock.yaml* ./
COPY --chown=node:node apps/frontend ./apps/frontend
COPY --chown=node:node packages ./packages

RUN pnpm install --frozen-lockfile

WORKDIR /app/apps/frontend
RUN pnpm run build

# ── Production target — static files served by Caddy ─────────────────────────
FROM caddy:2-alpine AS prod

LABEL org.opencontainers.image.title="Statflow Frontend (prod)" \
      org.opencontainers.image.description="Production-built Statflow SPA served by Caddy" \
      org.opencontainers.image.licenses="AGPL-3.0"

# Non-root user: Caddy on port 8080 does not need CAP_NET_BIND_SERVICE
RUN addgroup -g 1001 -S statflow \
    && adduser -u 1001 -S statflow -G statflow

COPY --from=builder /app/apps/frontend/dist /srv/statflow-frontend

# Caddy config: serve SPA with history-mode fallback (binds :8080 for non-root)
COPY docker/frontend/frontend.Caddyfile /etc/caddy/Caddyfile

RUN chown -R statflow:statflow /srv/statflow-frontend

USER statflow

EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=3 \
    CMD wget --quiet --tries=1 --spider http://localhost:8080/ || exit 1
