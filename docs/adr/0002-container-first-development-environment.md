# 0002 — Container-First Development Environment

Date: 2025-05-01

## Status

Accepted

## Context

Statflow is a polyglot system: PHP 8.3 (Symfony), TypeScript (Vue 3 + tracker), ClickHouse, PostgreSQL, and Redis.  Expecting contributors to install and maintain all of these runtimes on their host machines creates a high barrier to entry, leads to "works on my machine" bugs driven by version drift, and makes the CI environment diverge from local development.

Additionally, the production deployment model is container-based (Docker + FrankenPHP worker mode), so there is direct value in keeping development and production as close as possible.

## Decision

The development environment is **container-first**.  The only tools required on a contributor's host machine are Docker (≥ 24) with Compose v2 and `pnpm` (for workspace bootstrap scripts).  PHP, Composer, Node.js, database clients, and all other runtimes run exclusively inside Docker containers.

All recurring development tasks (lint, test, migrate, shell) are exposed via `make` targets that delegate to `docker compose exec` or `docker compose run --rm`.  There is no documented path for running services directly on the host.

## Consequences

- **Onboarding is reduced to three commands**: clone, copy `.env.example`, `docker compose up -d`.
- Development parity with production is maximised — both run the same Dockerfile.
- Contributors on Linux, macOS, and Windows (via WSL 2) work in an identical environment.
- All CI jobs replicate the same container-based approach, eliminating environment-specific failures.
- Performance on macOS with Docker Desktop may be slower than native due to filesystem virtualisation; contributors on macOS should enable VirtioFS or use a Linux VM.
- Some advanced IDE integrations (e.g. language servers expecting local binaries) require extra configuration to point at the containerised runtime.
