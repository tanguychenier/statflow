# 0005 — ClickHouse as Analytical Datastore

Date: 2025-05-01

## Status

Accepted

## Context

Statflow must store and query billions of immutable analytics events efficiently.  The access pattern is write-heavy (continuous high-volume ingestion), rarely mutating (events do not change after insertion), and read-intensive on large aggregations (e.g. "unique visitors per day for the last 90 days across all pages of a site").

These characteristics — append-mostly writes, columnar aggregation queries over large time ranges, no OLTP transactions — are a poor fit for a row-oriented relational database like PostgreSQL.

Datastores evaluated:

| Candidate | Why rejected |
|-----------|-------------|
| PostgreSQL + TimescaleDB | Good for time-series but struggles at analytics scale; aggregation queries over hundreds of millions of rows are slow without heavy partitioning work. |
| Apache Druid | Excellent at scale but operationally complex; heavy JVM footprint unsuitable for self-hosted small deployments. |
| DuckDB | Exceptional for single-node analytics but lacks a production-grade server mode and replication for multi-node deployments. |
| InfluxDB | Optimised for metrics/monitoring, not arbitrary event schemas; query language (Flux) is niche. |

## Decision

**ClickHouse** is the analytical datastore for all ingested events and derived aggregations.

Key reasons:

- Columnar storage with vectorised query execution — aggregation queries over billions of rows complete in milliseconds.
- The `MergeTree` family of engines (`ReplacingMergeTree`, `AggregatingMergeTree`, `SummingMergeTree`) enables efficient pre-aggregation materialised views alongside raw event storage.
- Native support for high-throughput batch inserts via the `Buffer` engine and HTTP interface.
- Lightweight self-hosted footprint — a single ClickHouse node handles hundreds of millions of events without the operational complexity of Druid or Spark.
- Active open-source community and commercially supported enterprise edition for organisations that need it.
- Built-in support for approximate distinct counting (`uniqHLL12`) and quantile estimation, which are core to analytics dashboards.

PostgreSQL remains the **application datastore** for transactional data: site configurations, user accounts, API keys, and other OLTP workloads.

## Consequences

- Ingestion events flow through Redis Streams → batch insert → ClickHouse, giving a durable buffer that decouples ingestion rate from write throughput.
- Analytical queries bypass PostgreSQL entirely, avoiding contention between OLTP and OLAP workloads.
- Schema evolution in ClickHouse requires care: `ALTER TABLE ADD COLUMN` is safe; removing or renaming columns requires a migration strategy.
- Joins across ClickHouse and PostgreSQL are not possible at the database level; cross-store queries must be orchestrated at the application layer.
- Contributors and operators must be familiar with ClickHouse's eventual-consistency model for `MergeTree` (rows may exist in multiple parts before being merged).
