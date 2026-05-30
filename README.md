<div align="center">

# Statflow

**Privacy-first, cookieless web &amp; product analytics — a self-hostable open-source alternative to Google Analytics**

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](apps/backend)
[![Symfony 7](https://img.shields.io/badge/Symfony-7.x-000000?logo=symfony&logoColor=white)](apps/backend)
[![Vue 3](https://img.shields.io/badge/Vue-3-4FC08D?logo=vue.js&logoColor=white)](apps/frontend)
[![ClickHouse](https://img.shields.io/badge/ClickHouse-analytics-FFCC01?logo=clickhouse&logoColor=black)](docs/data-model)
[![Self-hosted](https://img.shields.io/badge/self--hosted-Docker-2496ED?logo=docker&logoColor=white)](compose.yaml)

**Own your data. No cookies. No consent banner. One command to run.**

[Quick start](#-quick-start) · [Features](#-features) · [How it compares](#-how-it-compares) · [Architecture](#-architecture) · [Privacy](#-privacy--gdpr) · [Contributing](#-contributing)

</div>

---

Statflow is an open-source analytics platform that shows **how people actually use your site** — not just how many visited, but where they click, how they navigate, and where they drop off. It unifies the **audience measurement** of Plausible with the **behavioral/product analytics** of PostHog and Microsoft Clarity, in a single tool you host yourself.

Because it is **cookieless** and never stores personal data, you do not need a cookie-consent banner — and you keep **100% of your traffic** instead of losing the 20–30% that decline consent on cookie-based tools.

> **Status:** Milestone 1 is complete and proven end-to-end — ingestion pipeline, dashboard, and tracker all run against real infrastructure with a full local test suite. See the [roadmap](docs/roadmap/feature-roadmap.md).

## ✨ Features

- 📊 **Audience analytics** — visitors, pageviews, sessions, bounce rate, average duration, top pages, sources, devices, countries, UTM campaigns, with flexible date ranges and real-time view.
- 🖱️ **Behavioral analytics** — click &amp; scroll heatmaps, rage-click and dead-click detection, engagement time, and user journeys — the *why* behind the numbers.
- 🔻 **Funnels, retention &amp; segments** — multi-step conversion funnels, cohort retention, and saved segments that persist across reports.
- 🍪 **Cookieless &amp; private by design** — visitor identity is a salted, daily-rotated hash that is never persisted; no cookies, no cross-site tracking, no consent banner required.
- 🛡️ **Ad-blocker resistant** — first-party proxy mode serves the tracker and ingestion endpoint from *your* domain, so Brave and ad-blockers do not silently drop your data.
- 🪶 **Tiny tracker** — the core script is **under 4 KB gzipped**; heavy behavioral collectors load lazily, only when enabled.
- 🏠 **100% self-hosted** — runs entirely on your machine with `docker compose`. No external CDN, no third-party API, no telemetry. Geo-IP resolves from an embedded database.
- 👥 **Teams &amp; roles** — multi-user, multi-site, with owner / admin / editor / viewer roles and per-site public dashboards.

## 🚀 Quick start

You only need **Docker** (with Compose). Nothing else is installed on your machine — every tool runs in a container.

```bash
git clone https://github.com/tanguychenier/statflow.git
cd statflow
make setup
```

`make setup` is a single idempotent command: it builds the images, installs dependencies, generates local secrets, starts the data stores, runs the migrations, and brings up the API and dashboard. When it finishes:

- **Dashboard** → `http://localhost:5173`
- **API** → `http://localhost:8001`

Then add the tracker to any site you want to measure:

```html
<script>
  window.statflowConfig = { siteKey: 'stk_your_public_site_key' };
</script>
<script src="/sf/tracker.js" defer></script>
```

`apiBase` defaults to your own origin, so the snippet works as a **first-party** integration out of the box. See [docs/SETUP.md](docs/SETUP.md) for production deployment and the first-party proxy.

## 🆚 How it compares

| | **Statflow** | Google Analytics 4 | Plausible | PostHog | Matomo |
|---|:---:|:---:|:---:|:---:|:---:|
| Open source | ✅ AGPL-3.0 | ❌ | ✅ | ✅ | ✅ |
| Cookieless / no consent banner | ✅ | ❌ | ✅ | ⚠️ | ⚠️ |
| Audience analytics | ✅ | ✅ | ✅ | ⚠️ | ✅ |
| Behavioral (heatmaps, journeys) | ✅ | ❌ | ❌ | ✅ | 💰 paid add-on |
| Self-hosted, simple | ✅ one command | ❌ | ✅ | ⚠️ heavy | ✅ |
| Data stays 100% yours | ✅ | ❌ | ✅ | ✅ | ✅ |

Statflow targets the gap no single tool fills today: **behavioral depth + genuine data ownership + privacy by default**, with self-hosting that actually takes one command. See the full [competitive analysis](docs/roadmap/competitive-analysis.md).

## 🏗️ Architecture

A monorepo with a strict separation between a pure REST API and a standalone SPA, plus the tracking script:

```
apps/backend     Symfony 7 · hexagonal architecture · CQRS · FrankenPHP — pure REST API
apps/frontend    Vue 3 · Vite · Pinia · TanStack Query · Tailwind CSS 4 — dashboard SPA
packages/tracker Vanilla TypeScript · zero-dependency tracking script
docker/          Container images and service configuration
docs/            Architecture, ADRs, API (OpenAPI), data model, design system
```

**Ingestion pipeline:** the tracker sends a compact event → the API validates, anonymizes, and buffers it in **Redis Streams** → a batch worker writes it to **ClickHouse** → the dashboard queries aggregated metrics. PostgreSQL holds accounts, sites, and configuration.

The backend follows **hexagonal architecture** (ports &amp; adapters) across five bounded contexts — Ingestion, Analytics, Identity, Sites, Reporting — with boundaries enforced in CI by Deptrac. More in [docs/architecture.md](docs/architecture.md) and the [ADRs](docs/adr).

### Tech stack

**Backend** PHP 8.3 · Symfony 7 · FrankenPHP · Doctrine · Symfony Messenger
**Frontend** Vue 3 · TypeScript · Vite · Pinia · vue-router · TanStack Query · Tailwind CSS 4 · Apache ECharts
**Data** ClickHouse (analytical) · PostgreSQL (application) · Redis (ingestion buffer + cache)
**Tooling** Docker · PHPUnit · Vitest · Playwright · PHPStan · Deptrac · ECS · Rector

## 🔒 Privacy &amp; GDPR

- **No cookies, no local storage of identifiers.** Visitor and session IDs are derived server-side as `HMAC-SHA256` over IP + user-agent with a **daily-rotated salt that is never persisted** — cross-day re-identification is cryptographically impossible after the fact.
- **Cross-site isolation** is built into the hash key, so the same visitor on two sites is never linkable.
- **Raw IP is discarded** immediately after hashing and geo-lookup; form values, query-string PII, and credentials are stripped before anything leaves the browser.
- **Do Not Track** and **Global Privacy Control** are honored by default.

Because no data is linked to an identifiable individual and no device storage is accessed, the architecture does not require ePrivacy consent. See [docs/data-model/identity-and-privacy.md](docs/data-model/identity-and-privacy.md).

## 🧑‍💻 Development

Everything runs through `make` — nothing is installed on the host beyond Docker.

```bash
make setup     # one-command bootstrap from a fresh clone
make up        # start the stack
make ci        # run the FULL quality gate locally (lint + static analysis + tests + build)
make test      # backend + frontend + tracker test suites
make lint      # polyglot lint suite
make down      # stop the stack
```

> **CI runs locally.** `make ci` reproduces the entire pipeline on your machine — there is no reliance on a paid cloud runner. The bundled GitHub Actions workflow is manual-only and consumes no minutes.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the workflow, commit conventions, and how to run each gate.

## 🗺️ Roadmap

- **Milestone 1 — Plausible parity:** core audience metrics, real-time, multi-site, public dashboards, anti-adblock proxy. ✅
- **Milestone 2 — GA4 parity:** custom events &amp; conversions, goals, funnels, segments, retention, roles.
- **Milestone 3 — Beyond GA:** full heatmaps &amp; session journeys, data API &amp; exports, scheduled email reports, e-commerce revenue, embeddable dashboards.

Full detail in [docs/roadmap/feature-roadmap.md](docs/roadmap/feature-roadmap.md).

## 🤝 Contributing

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md) and the [Code of Conduct](CODE_OF_CONDUCT.md). Found a security issue? Please follow [SECURITY.md](SECURITY.md).

## 📄 License

Statflow is licensed under the **GNU Affero General Public License v3.0** — see [LICENSE](LICENSE). The AGPL keeps Statflow and every hosted derivative open source.

Copyright © 2026 [Tanguy Chénier](https://github.com/tanguychenier).
