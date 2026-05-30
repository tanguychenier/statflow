# Contributing to Statflow

Copyright (C) 2026 Tanguy Chénier. Released under the GNU Affero General Public License v3.0 (AGPL-3.0).

Thank you for taking the time to contribute. This document covers everything you need
to get a working development environment, understand our conventions, and submit
high-quality pull requests.

---

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Prerequisites](#prerequisites)
3. [Getting Started](#getting-started)
4. [Project Layout](#project-layout)
5. [Development Workflow](#development-workflow)
6. [Coding Standards & Linting](#coding-standards--linting)
7. [Running Tests](#running-tests)
8. [Commit Convention](#commit-convention)
9. [Pull Request Process](#pull-request-process)
10. [Release Process](#release-process)

---

## Code of Conduct

All contributors are expected to follow our [Code of Conduct](CODE_OF_CONDUCT.md).
Please read it before participating.

---

## Prerequisites

Statflow is **container-first**: nothing is installed directly on your host machine
except the following two tools.

| Tool | Minimum version | Purpose |
|------|----------------|---------|
| [Docker](https://docs.docker.com/get-docker/) + Docker Compose v2 | 24.x / 2.x | Run every service and build step |
| [pnpm](https://pnpm.io/installation) | 9.x | Monorepo workspace management on the host (scripts only) |

> **No PHP, Node, Composer, or database clients need to be installed on your host.**
> Every build, lint, and test command is executed inside a container.

---

## Getting Started

```bash
# 1. Fork the repository on GitHub, then clone your fork
git clone https://github.com/<your-username>/statflow.git
cd statflow

# 2. Copy the environment template
cp .env.example .env
# Review .env and adjust any local overrides (ports, passwords, etc.)

# 3. One command does everything: build images, install dependencies, generate
#    secrets, run database migrations, and start the stack.
make setup
```

`make setup` is idempotent — re-running it is safe and skips work that is already
done (existing secrets are never overwritten). See
[docs/SETUP.md](docs/SETUP.md) for the full step-by-step breakdown of what it
provisions.

The frontend will be available at `http://localhost:${FRONTEND_PORT:-5173}` and
the API at `http://localhost:${BACKEND_PORT:-8000}`.

---

## Project Layout

```
statflow/
├── apps/
│   ├── backend/        # Symfony 7 · PHP 8.3+ · FrankenPHP · hexagonal architecture
│   └── frontend/       # Vue 3 · TypeScript · Vite · Pinia · shadcn-vue
├── packages/
│   └── tracker/        # Vanilla TS tracking script (<2 KB gzipped)
├── docker/             # Dockerfiles and build contexts
├── docs/
│   ├── adr/            # Architecture Decision Records (Nygard format)
│   └── architecture.md # High-level system overview
├── compose.yaml        # Docker Compose orchestration
└── Makefile            # Convenience targets (delegates to docker compose / pnpm)
```

---

## Development Workflow

All recurring tasks are exposed via `make` targets that delegate to Docker Compose.
Run `make help` to list them.

### Backend (Symfony / PHP)

```bash
# Enter the backend container shell
make sh-backend

# Run a specific Symfony command inside the container, e.g. migrations:
# make sh-backend → php bin/console doctrine:migrations:migrate

# Clear the Symfony cache inside the container:
# make sh-backend → php bin/console cache:clear
```

### Frontend (Vue 3)

```bash
# The Vite dev server with HMR runs automatically via docker compose up.
# To run an ad-hoc command inside the container:
make sh-frontend
```

### Tracker

```bash
# There is no dedicated tracker shell target; run commands via pnpm at the
# workspace root or inside the frontend container:
# pnpm --filter @statflow/tracker build  →  dist/statflow.js
```

---

## Coding Standards & Linting

All checks run inside containers — no local tooling required.

### Backend

| Tool | Purpose | Command |
|------|---------|---------|
| PHP-CS-Fixer (ECS) | Code style (PSR-12 + Symfony rules) | `make lint` |
| PHPStan (level 9) | Static analysis | `make stan` |
| Rector | Automated refactoring (dry-run in CI) | `make rector` |

### Frontend / Tracker

| Tool | Purpose | Command |
|------|---------|---------|
| ESLint | TS/Vue linting | `make lint` |
| vue-tsc | TypeScript type checking | run via `pnpm --filter @statflow/frontend typecheck` |
| Prettier (via ESLint) | Formatting | included in `make fix` |

### Infrastructure / Config

| Tool | Purpose |
|------|---------|
| hadolint | Dockerfile linting |
| yamllint | YAML linting |
| actionlint | GitHub Actions validation |
| markdownlint | Markdown consistency |
| editorconfig-checker | EditorConfig compliance |
| gitleaks | Secret scanning |

These run in the `lint` CI job using pinned Docker images — no local installation
needed. Run `make lint` locally to execute the full suite.

---

## Running Tests

### Backend (PHPUnit)

```bash
# Run the full backend test suite
make test-backend

# Run a specific test file or filter (enter the container first)
make sh-backend
# Inside: php bin/phpunit --filter=MyFeatureTest
```

Tests follow the same hexagonal structure as the source code:

```
apps/backend/tests/
├── Unit/          # Domain layer — no framework, no I/O
├── Integration/   # Application layer — real databases (via test containers)
└── E2E/           # HTTP-level — full stack
```

### Frontend (Vitest)

```bash
make test-frontend
# or inside the frontend container: pnpm vitest run
```

### Tracker (Vitest)

```bash
make test-tracker
# Bundle size is also checked: pnpm size-limit
```

---

## Commit Convention

Statflow uses [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/)
enforced by `commitlint` via Lefthook.

### Format

```
<type>(<optional scope>): <short summary>

[optional body]

[optional footers]
```

### Allowed types

| Type | When to use |
|------|------------|
| `feat` | A new feature |
| `fix` | A bug fix |
| `perf` | Performance improvement |
| `refactor` | Code change that neither fixes a bug nor adds a feature |
| `test` | Adding or correcting tests |
| `docs` | Documentation only |
| `build` | Build system or dependency changes |
| `ci` | CI configuration changes |
| `chore` | Maintenance (no source or test change) |
| `revert` | Reverts a previous commit |

### Scopes (non-exhaustive)

`backend`, `frontend`, `tracker`, `docker`, `ci`, `adr`, `ingestion`, `analytics`,
`identity`, `sites`, `reporting`

### Examples

```
feat(tracker): add session-duration heartbeat event
fix(backend): prevent duplicate site slug on creation
docs(adr): add ADR-0007 for Redis Streams ingestion buffer
ci: cache pnpm store in frontend job
```

---

## Pull Request Process

1. **Branch naming** — `<type>/<short-slug>` e.g. `feat/heatmap-rendering` or
   `fix/tracker-spa-navigation`.
2. **Keep PRs focused** — one logical change per PR. Large features should be split
   into smaller, independently-reviewable pieces.
3. **Fill in the PR template** — describe what changes, why, and how to test it.
4. **All CI checks must pass** before requesting review.
5. **At least one maintainer approval** is required to merge.
6. **Merge strategy** — squash-merge into `main`; the squash commit message must
   follow the Conventional Commits format.
7. **Branch cleanup** — delete your branch after merge.

### Review turnaround

We aim to provide an initial review within **5 business days**. Complex changes may
take longer.

---

## Release Process

Releases follow [Semantic Versioning](https://semver.org/). Changelogs are generated
automatically from Conventional Commits using `conventional-changelog`. Maintainers
cut releases; contributors do not need to worry about this step.

---

## Questions?

Open a [GitHub Discussion](https://github.com/tansoftware/statflow/discussions) —
issues are reserved for actionable bugs and feature requests.
