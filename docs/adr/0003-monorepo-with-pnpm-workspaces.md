# 0003 — Monorepo with pnpm Workspaces

Date: 2025-05-01

## Status

Accepted

## Context

Statflow consists of several closely related but independently deployable artefacts:

- `apps/backend` — Symfony API
- `apps/frontend` — Vue 3 SPA
- `packages/tracker` — tiny TS bundle consumed by end-user websites

Changes frequently span multiple artefacts.  For example, a new event type requires updating the tracker payload, the backend ingestion endpoint, and the dashboard visualisation simultaneously.  Managing these as separate repositories would mean coordinating multiple PRs, version bumps, and release sequences for every cross-cutting change.

Alternatives considered:

- **Separate repositories** — simple individually, but cross-cutting changes are expensive to coordinate and test atomically.
- **Turborepo** — adds a caching daemon and a separate config layer; useful at larger scale but overkill for a three-workspace project.
- **Nx** — powerful, but introduces significant tooling complexity and opinionated conventions.

## Decision

We use a single **monorepo** managed by [pnpm workspaces](https://pnpm.io/workspaces) with the workspace layout `apps/*` and `packages/*`.  pnpm is chosen over npm/yarn workspaces for its strict `node_modules` isolation (no phantom dependencies), fast installs via a content-addressable store, and native workspace filtering (`pnpm --filter`).

The PHP backend (`apps/backend`) participates in the monorepo directory structure for colocation but manages its own dependencies via Composer; it is not a pnpm workspace package.

Root-level tooling (commitlint, Lefthook, markdownlint, editorconfig) is installed at the workspace root and applies across all packages.

## Consequences

- A single `git clone` gives contributors everything they need.
- Atomic commits across frontend, tracker, and documentation are natural.
- Cross-package refactors and type sharing are straightforward.
- CI can filter jobs by changed paths, keeping pipelines fast.
- The pnpm content-addressable store significantly reduces `node_modules` disk usage compared to npm.
- The monorepo will grow in complexity as more apps or packages are added; if it becomes unwieldy, a build orchestration layer (Turborepo or Nx) can be layered on without restructuring the directory tree.
