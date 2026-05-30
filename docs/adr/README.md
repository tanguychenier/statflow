# Architecture Decision Records

This directory contains the Architecture Decision Records (ADRs) for Statflow, using the [Nygard format](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions).

---

## What is an ADR?

An ADR is a short document that captures an architecturally significant decision: the context that drove it, the decision itself, and its consequences.  ADRs are written once and then treated as immutable history.  If a decision is reversed, a new ADR is written to supersede the old one — the old record remains in place.

---

## ADR format (Nygard)

```markdown
# NNNN — Title

Date: YYYY-MM-DD

## Status

Proposed | Accepted | Deprecated | Superseded by [NNNN](./NNNN-title.md)

## Context

The situation that motivated this decision.

## Decision

The change we are making or have made.

## Consequences

What becomes easier, harder, or different as a result.
```

---

## Index

| # | Title | Status |
|---|-------|--------|
| [0001](./0001-record-architecture-decisions.md) | Record Architecture Decisions | Accepted |
| [0002](./0002-container-first-development-environment.md) | Container-First Development Environment | Accepted |
| [0003](./0003-monorepo-with-pnpm-workspaces.md) | Monorepo with pnpm Workspaces | Accepted |
| [0004](./0004-hexagonal-architecture-with-cqrs.md) | Hexagonal Architecture with CQRS | Accepted |
| [0005](./0005-clickhouse-as-analytical-datastore.md) | ClickHouse as Analytical Datastore | Accepted |
| [0006](./0006-event-based-unified-data-model.md) | Event-Based Unified Data Model | Accepted |
| [0007](./0007-canonical-event-contract.md) | Canonical Event Contract | Accepted |
| [0008](./0008-cookieless-identity-model.md) | Cookieless Identity Model | Accepted |
| [0009](./0009-authentication-and-authorization-model.md) | Authentication and Authorization Model | Accepted |

---

## How to add a new ADR

1. Pick the next sequential number (`NNNN`).
2. Copy the format template above into `docs/adr/NNNN-<kebab-case-title>.md`.
3. Set the status to `Proposed` until the decision is agreed by the maintainers.
4. Open a PR; discussion happens in the PR review.
5. On merge, update the index table above and change the status to `Accepted`.
6. If the ADR supersedes an earlier one, update the old ADR's status field.

Use the commit scope `adr`, e.g. `docs(adr): add ADR-0007 redis-streams-ingestion-buffer`.
