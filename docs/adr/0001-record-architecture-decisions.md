# 0001 — Record Architecture Decisions

Date: 2025-05-01

## Status

Accepted

## Context

As Statflow grows, individual architectural choices — why we chose ClickHouse over TimescaleDB, why the tracker is vanilla TypeScript, why the monorepo uses pnpm — will otherwise be scattered across pull requests, design documents, or developer memory.  New contributors and future maintainers will repeatedly ask "why does it work this way?" without a reliable answer.

We need a lightweight, version-controlled mechanism that captures significant decisions alongside the code they affect, without introducing heavy tooling or process overhead.

## Decision

We will use Architecture Decision Records (ADRs) in the [Nygard format](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions), stored as Markdown files in `docs/adr/`.  Each file is named `NNNN-<kebab-title>.md` where `NNNN` is a zero-padded sequential number.

ADRs are immutable once accepted.  Superseded decisions are marked as such; the original record is never deleted.

## Consequences

- The rationale behind key choices is always findable via `git log` and GitHub search.
- New contributors can onboard faster by reading the ADR index rather than mining PR history.
- The process introduces minimal friction: a short Markdown file per significant decision.
- Writers must exercise judgment about what is "architecturally significant"; not every implementation detail warrants an ADR.
