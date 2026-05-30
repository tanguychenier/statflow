# 0004 — Hexagonal Architecture with CQRS

Date: 2025-05-01

## Status

Accepted

## Context

The Statflow backend is responsible for high-throughput event ingestion (write path) and complex analytical query serving (read path) — workloads with fundamentally different performance profiles and scaling requirements.  A traditional layered architecture tends to couple these paths together, making it hard to optimise each independently and leading to test suites that depend on the full framework stack.

Maintainability and testability are first-class concerns: the codebase must be navigable by contributors unfamiliar with Symfony internals, and the domain logic must be testable in isolation without booting the framework or hitting a database.

## Decision

The backend adopts **hexagonal architecture** (ports and adapters, also known as the clean architecture variant): the domain and application layers are framework-agnostic; all I/O is mediated through explicit port interfaces whose adapters live in the infrastructure layer.

**CQRS** (Command-Query Responsibility Segregation) is implemented using **Symfony Messenger** with three dedicated message buses:

| Bus | Purpose |
|-----|---------|
| `command.bus` | Mutating operations (write side); commands are handled exactly once. |
| `query.bus` | Read operations; queries return DTOs without side effects. |
| `event.bus` | Domain events raised by command handlers; consumed asynchronously by projectors and listeners. |

Bounded contexts (`Ingestion`, `Analytics`, `Identity`, `Sites`, `Reporting`, `Shared`) map to top-level namespaces inside `apps/backend/src/`.  Cross-context communication happens exclusively through the event bus or explicit application-service calls — never via direct repository or entity coupling.

**Deptrac** enforces layer and context boundaries in CI; violations cause the build to fail.

## Consequences

- Domain logic is testable without the Symfony kernel, databases, or HTTP — unit tests are fast and isolated.
- The write path (command bus → ingestion) and read path (query bus → ClickHouse / PostgreSQL) can be scaled and evolved independently.
- The explicit port/adapter model makes it straightforward to swap infrastructure components (e.g. replace Redis Streams with Kafka) without touching domain code.
- There is more boilerplate than a typical Symfony CRUD app: every use case requires a Command/Query class, a Handler, and often a DTO.  This overhead pays off at scale but can feel heavy for trivial operations.
- New contributors from a "classic" Symfony background must learn the architecture conventions before being productive; the ADR index and `docs/architecture.md` serve as onboarding material.
