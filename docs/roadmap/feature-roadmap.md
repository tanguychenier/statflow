# Statflow — Feature Roadmap

**Last updated:** May 2026  
**Strategy:** Ship a production-quality, privacy-first analytics platform in three milestones — each one independently valuable and deployable, each one obsoleting a competitor.

---

## Guiding Principles

1. **Every milestone delivers a shippable, coherent product.** Jalon 1 alone replaces Plausible. Jalons 1+2 replace GA4. All three replace the GA4 + Clarity + PostHog combination.
2. **Behavioral analytics are never behind a paywall.** The full feature set is always free in the self-hosted tier, always.
3. **Privacy is structurally enforced, not a checkbox.** Anonymization happens in the ingestion layer before any write. No raw IPs, no fingerprints, no cross-site tracking.
4. **Self-hosting must be one command.** Complexity is our enemy for adoption. Every architectural decision that increases ops burden requires explicit justification.
5. **Open core, not open bait.** AGPL-3.0 for the entire platform. Enterprise features (SSO, embeddable dashboards, advanced RBAC) are in the same repo, same license — not a commercial tier.

---

## Milestone Map

```
Jalon 1 ─── Plausible parity        → Cookieless audience measurement, done right
Jalon 2 ─── GA4 parity              → Full event model, goals, funnels, retention
Jalon 3 ─── Beyond GA4              → Behavioral layer, integrations, e-commerce
```

---

## Jalon 1 — Plausible Parity

**Theme:** "The privacy-first Google Analytics replacement that works without a consent banner."  
**Target users:** Web publishers, indie developers, small SaaS teams, privacy-conscious businesses.  
**Definition of done:** A team currently on Plausible (or considering it) can migrate to Statflow self-hosted with zero loss of features and a meaningful gain in data ownership.

### Core Tracking Infrastructure

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 1.1 | Cookieless unique visitor counting (IP + UA daily-rotating salt, in-memory only — never persisted) | Critical | The privacy guarantee is structural. Implement per the Plausible architecture. |
| 1.2 | Tracking script < 2 KB (minified + gzip) | Critical | Performance is a feature. Adhere to this as a hard constraint in CI. |
| 1.3 | Pageview event ingestion (endpoint, schema, batching) | Critical | Single-tenant and multi-tenant ingestion paths from day one. |
| 1.4 | Bot and spider filtering (IP reputation list + UA pattern matching) | High | Prevents artificial inflation of metrics. |
| 1.5 | Referrer chain normalization (strip tracking params, group search engines) | High | Referrer data is often noisy; consistent normalization is essential. |
| 1.6 | Campaign source attribution via UTM parameters (utm_source, utm_medium, utm_campaign, utm_content, utm_term) | High | Required for any marketing team to adopt Statflow. |

### Dashboard — Audience Metrics

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 1.7 | Real-time visitor count (last 5 minutes, active pages) | Critical | Trust-building feature; users expect it on day one. |
| 1.8 | Visitors / unique visitors / pageviews / sessions | Critical | Primary KPIs on every dashboard. |
| 1.9 | Bounce rate and average session duration | Critical | |
| 1.10 | Traffic sources report (referrers, search engines, UTM campaigns, direct, social) | Critical | |
| 1.11 | Top pages report (with entry/exit breakdown) | Critical | |
| 1.12 | Devices report (device type, OS, browser) | High | |
| 1.13 | Geographic report (country, region, city) | High | Use IP-to-geo without storing the IP. |
| 1.14 | Flexible date ranges (last 7/30/90/12 months, custom picker, comparison periods) | Critical | |
| 1.15 | Aggregated multi-site dashboard | High | Essential for agencies and operators managing multiple properties. |

### Privacy Architecture

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 1.16 | No PII write path — anonymization at ingestion boundary before any database write | Critical | Non-negotiable design constraint. Documented in ADR. |
| 1.17 | GDPR/CCPA/PECR compliance mode on by default | Critical | No consent banner required for EU deployments in default mode. |
| 1.18 | Data residency: user chooses storage location at install time | High | |
| 1.19 | Data deletion API (right to erasure — no-op for cookieless as no personal data exists; document this clearly) | Medium | |

### Self-Hosting & Operations

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 1.20 | Single `docker compose up` installation (ingestion service + ClickHouse + UI) | Critical | Must work on a $6/month VPS. Document this explicitly in README. |
| 1.21 | Built-in reverse proxy / anti-adblock first-party endpoint (proxied under site's own domain) | High | Increases data accuracy by 15–25% in typical European markets. |
| 1.22 | Environment-variable-based configuration (no config file editing required for basic setup) | High | |
| 1.23 | Automatic HTTPS via Caddy integration | Medium | Reduces friction for non-technical operators. |
| 1.24 | Basic admin UI: add/remove sites, regenerate tracking keys | High | |

### Sharing & Transparency

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 1.25 | Public dashboard mode (anyone with the URL can view) | High | Strong adoption driver; open-source projects and indie devs love this. |
| 1.26 | Password-protected shareable dashboard link | Medium | Agency use case. |
| 1.27 | Embeddable iframe for public stats widget | Low | Nice-to-have for portfolio pages and open-source project READMEs. |

### Jalon 1 — Acceptance Criteria

- A Plausible self-hosted user can export their data and have equivalent reports in Statflow within one hour.
- Tracking script scores < 2 KB over the wire.
- No request to a tracking endpoint stores a raw IP address or any hash that persists beyond the current UTC day.
- The platform passes a GDPR technical audit (no cookies, no PII, no cross-session linking).
- Full installation from `docker compose up` to first pageview in < 15 minutes on a clean VPS.

---

## Jalon 2 — GA4 Parity

**Theme:** "Everything Google Analytics 4 offers, on your infrastructure, without the privacy compromise."  
**Target users:** Growth and marketing teams, SaaS product managers, data analysts who currently use GA4 but are frustrated by consent loss, data opacity, or compliance risk.  
**Definition of done:** A team currently on GA4 can run Statflow alongside for 30 days, see equivalent or superior reporting, and feel confident removing GA4.

*All Jalon 1 features are prerequisites.*

### Event Model & Custom Tracking

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 2.1 | Custom event API (name + arbitrary property map) | Critical | The foundation of all product analytics. |
| 2.2 | Autocapture SDK (clicks, form submissions, outbound links, JS errors, scroll depth — zero config) | Critical | Match PostHog's zero-instrumentation experience. |
| 2.3 | Custom dimensions and metrics (up to 50 of each per site) | High | Required for business-specific reporting. |
| 2.4 | Event deduplication and idempotency guarantees | High | Data quality is trust. |
| 2.5 | Server-side event ingestion endpoint (for server-to-server tracking, immune to adblock) | High | |
| 2.6 | Enhanced measurement (automatic scroll, outbound click, file download, 404 tracking) | Medium | |

### Goals & Conversions

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 2.7 | Goal definition UI (URL-based, event-based, duration-based, pages/session-based) | Critical | |
| 2.8 | Conversion rate tracking per goal | Critical | |
| 2.9 | Revenue attribution to goals (custom event revenue property) | High | |
| 2.10 | Goal-based filtering across all reports | High | |

### Funnel Analysis

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 2.11 | Multi-step funnel builder (URL steps, event steps, or mixed; up to 16 steps) | Critical | Exceeds Plausible's 8-step limit. |
| 2.12 | Funnel conversion rates per step with drop-off visualization | Critical | |
| 2.13 | Funnel segmentation by dimension (source, device, country, custom property) | High | |
| 2.14 | Funnel time-to-convert histogram | Medium | |

### Segments & Filters

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 2.15 | Saved segments (reusable filter sets) | High | |
| 2.16 | Comparison segments (A vs. B on the same chart) | High | |
| 2.17 | Filter by any event property, UTM field, dimension, device, or geography | Critical | |
| 2.18 | Cross-report filter persistence (apply a segment once, see it everywhere) | High | |

### Retention & Cohort Analysis

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 2.19 | Retention matrix (N-day, N-week, N-month return rate) | High | Cookieless retention requires a consistent anonymous identifier scoped to the session sequence — design this carefully. |
| 2.20 | Cohort analysis by acquisition date, first event, or first campaign | High | |
| 2.21 | Lifecycle segmentation (new, returning, at-risk, churned visitors) | Medium | |

### User Journeys & Path Analysis

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 2.22 | Forward path exploration (from entry page: where did users go next?) | High | |
| 2.23 | Backward path exploration (before conversion: what path did users take?) | High | |
| 2.24 | Sankey-style flow visualization | Medium | |
| 2.25 | Top exit pages per funnel step | High | |

### Campaign & Attribution

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 2.26 | UTM campaign dashboard with conversion attribution | High | |
| 2.27 | First-touch / last-touch / linear attribution models | Medium | |
| 2.28 | Referral chain report (multi-hop source tracking within a session) | Medium | |

### Multi-User & Access Control

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 2.29 | User accounts (email + password, invite-based) | Critical | Required for any team adoption. |
| 2.30 | Role-based access: Owner, Admin, Editor, Viewer | High | |
| 2.31 | Per-site access grants | High | |
| 2.32 | API key management per user | High | |
| 2.33 | Audit log of admin actions | Medium | |

### Data Access & Integrations

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 2.34 | Stats REST API (replicate Plausible's Stats API surface, add custom event queries) | High | |
| 2.35 | Webhooks (event triggers: goal achieved, anomaly detected, threshold crossed) | Medium | |
| 2.36 | Google Search Console integration (overlay organic search queries on top pages) | Medium | |
| 2.37 | CSV export for any report | High | |

### Jalon 2 — Acceptance Criteria

- A GA4 event schema (gtag.js custom events + conversions) can be migrated to Statflow's event API with a documented migration guide.
- Funnel and retention reports produce results consistent with GA4 Explorations for the same dataset, within statistical noise.
- Multi-user roles enforce least-privilege — a Viewer cannot modify goals, access the API, or export raw data unless granted explicitly.
- Stats API passes an integration test suite covering all core report endpoints.

---

## Jalon 3 — Beyond GA4

**Theme:** "The last analytics tool you'll ever need — audience, behavioral, and business intelligence in one open platform."  
**Target users:** Teams that currently pay for GA4 + Clarity + PostHog + a BI tool. Statflow collapses all four into one self-hosted, privacy-preserving platform.  
**Definition of done:** A team can retire all four external analytics tools and replace them entirely with Statflow.

*All Jalon 1 and 2 features are prerequisites.*

### Behavioral Analytics — Heatmaps

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 3.1 | Click maps (aggregated click density over DOM snapshot) | Critical | Core behavioral insight. Must work without storing personal data. |
| 3.2 | Scroll depth maps (fold visibility by percentage of users) | Critical | |
| 3.3 | Move / hover maps (mouse movement trajectory aggregation) | Medium | |
| 3.4 | Rage-click and dead-click detection | High | Match Clarity's flagship UX frustration signals. |
| 3.5 | Mobile vs. desktop heatmap views | High | |
| 3.6 | Heatmap filtered by segment (funnel step, device, source) | High | This is the key differentiator vs. Clarity — behavioral data linked to analytical context. |
| 3.7 | Heatmap history: compare two time periods side by side | Medium | |

### Behavioral Analytics — Session Journeys

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 3.8 | Session replay with automatic PII masking (input fields, credit card numbers) | High | Privacy-preserving: mask by default, allow explicit un-masking per element class. |
| 3.9 | Session filtering by funnel step, goal, segment, or rage-click signal | High | Enables qualitative investigation of quantitative anomalies. |
| 3.10 | Session tagging and annotation | Medium | |
| 3.11 | Linked heatmap ↔ session replay (click on a heatmap hotspot, see sessions that generated it) | High | The unified data model makes this possible. No competitor does this cleanly. |
| 3.12 | AI-assisted session summary (key moments, detected frustration signals, page flow summary) | Medium | Reduces review time from hours to minutes. |

### Data API & Exports

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 3.13 | Full raw event export (JSON, CSV, Parquet) | High | Data ownership means data portability. |
| 3.14 | ClickHouse direct query access for self-hosted deployments (document the schema) | High | Power users who want direct SQL access should have it without an abstraction layer. |
| 3.15 | Scheduled report exports (daily/weekly/monthly email digests) | High | Replaces GA4's scheduled email reports. |
| 3.16 | Streaming export via webhook (per-event, near-real-time) | Medium | ETL / data warehouse integration use case. |
| 3.17 | Embeddable dashboard widgets (signed iframes, configurable per site) | High | Agency / SaaS product use case. |

### Alerting & Monitoring

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 3.18 | Anomaly detection alerts (traffic spike, goal conversion drop, error rate spike) | High | |
| 3.19 | Threshold-based alerts (e.g., "alert me when daily visitors < 500") | High | |
| 3.20 | Alert delivery via email, Slack webhook, or custom webhook | Medium | |
| 3.21 | Site uptime / script availability monitoring | Low | |

### E-commerce & Revenue

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 3.22 | Order / transaction event schema (order_id, revenue, tax, shipping, items) | High | |
| 3.23 | Revenue dashboard (total revenue, average order value, revenue per source) | High | |
| 3.24 | Product performance report (impressions, add-to-cart rate, purchase rate per SKU) | Medium | |
| 3.25 | Customer lifetime value (CLV) approximation by cohort | Medium | Requires consistent anonymous ID over time — design for cookieless LTV carefully. |
| 3.26 | Checkout funnel pre-built template | High | |

### GDPR & Privacy Tooling

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 3.27 | Integrated consent mode: if a site chooses to use cookies/consent, Statflow degrades gracefully and tracks consented users separately from anonymous sessions | High | Some clients will run in a hybrid mode — support it without judgment. |
| 3.28 | Data processing agreement (DPA) generator (self-hosted operators need this for their own clients) | Medium | |
| 3.29 | Automated data retention policies (delete events older than N days per site, configurable) | High | |
| 3.30 | IP exclusion list (self-hosted operators can exclude their own office IPs) | Medium | |
| 3.31 | Visitor opt-out endpoint (respects the Plausible opt-out standard via localStorage flag) | High | |

### Enterprise & Extensibility

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 3.32 | SSO / SAML 2.0 support | Medium | Enterprise deployments require this. |
| 3.33 | SCIM user provisioning | Low | |
| 3.34 | Plugin / integration SDK (documented event ingestion hook + UI extension points) | Medium | Enable community integrations (WordPress, Shopify, Next.js, etc.). |
| 3.35 | White-label mode (custom logo, color scheme, custom domain for embedded dashboards) | Medium | Agency reseller use case. |

### Jalon 3 — Acceptance Criteria

- Heatmap data collection adds < 3 KB to the tracking script with lazy loading.
- Session replay never records a raw password field, payment field, or email field by default.
- A heatmap filter by funnel segment returns results in < 3 seconds at 10 M events on a single-node deployment.
- Anomaly detection generates zero false-positive alerts in a 7-day test on a property with stable traffic.
- The data export pipeline produces a Parquet file of 1 year of events in < 10 minutes on commodity hardware.

---

## Cross-Cutting Concerns (All Milestones)

These are not tied to a single milestone — they run through every sprint.

| Area | Requirement |
|------|-------------|
| Performance | All dashboard queries < 1 second at 10 M events on a single ClickHouse node |
| Test coverage | Unit + integration tests > 80% on ingestion and query layers |
| Accessibility | Dashboard WCAG 2.1 AA compliant |
| Documentation | Every public API endpoint documented with examples; self-hosting guide updated on every release |
| Changelog | Public changelog entry for every release, categorized by user impact |
| Security | Dependency audit on every CI run; OWASP Top 10 checklist per milestone |
| Upgrade path | Zero-downtime ClickHouse schema migrations via versioned migration runner |
| SDK coverage | JavaScript (vanilla + npm), TypeScript, React, Next.js server components, Python, Go, PHP adapters |

---

## Milestone Timeline (Indicative)

| Milestone | Scope | Estimated effort | Marker |
|-----------|-------|-----------------|--------|
| Jalon 1 | Features 1.1 – 1.27 | ~4 months (small core team) | First public release / v1.0 |
| Jalon 2 | Features 2.1 – 2.37 | ~5 months | v2.0 — GA4 parity |
| Jalon 3 | Features 3.1 – 3.35 | ~6 months | v3.0 — full platform |

Timelines assume a core team of 3–4 engineers. Community contributions can accelerate Jalon 3 significantly once the platform has early adopters from Jalons 1 and 2.

---

## Deprioritized / Out of Scope (For Now)

The following were considered and deliberately deferred:

- **A/B testing and feature flags** — PostHog owns this space; integrating with PostHog or Unleash via a documented adapter is a better use of resources than building from scratch.
- **LLM / AI-native analytics** — Valuable but requires significant data volumes to be meaningful. Revisit after Jalon 3 when the user base is large enough.
- **Mobile SDK (iOS, Android)** — Important long-term; requires a separate bounded context and SDK surface. Roadmap candidate for Jalon 4.
- **CDP / user identity stitching** — Conflicts with the privacy model. Requires a deliberate design decision and community discussion before implementation.
