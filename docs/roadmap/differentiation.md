# Statflow — Positioning & Differentiation

**Last updated:** May 2026  
**Audience:** Founders, contributors, early adopters, and anyone asking "why Statflow instead of X?"

---

## The Problem Statement

Today, a team that wants complete, accurate, privacy-preserving insight into their web product has exactly two options — both bad:

**Option A: Use Google Analytics 4.**  
Comprehensive data, but you surrender control. GA4 sends data to US servers under terms that multiple EU data protection authorities have ruled non-compliant with GDPR. You lose 20–30% of EU data because users decline the consent banner you are legally required to show. Your most sensitive business metrics live in Google's infrastructure, used in ways you cannot audit.

**Option B: Assemble a privacy-first stack.**  
Use Plausible for audience metrics, PostHog or Clarity for behavioral analytics, and Matomo or a BI tool for anything advanced. You now operate three separate platforms, pay three separate bills, store three separate datasets, and — critically — can never answer the question: "What is the behavioral pattern of users who converted via this specific campaign, on mobile, in Germany?" because the data is siloed across tools that do not share an event model.

**Statflow is the third option: a single, unified, fully open-source, privacy-by-design analytics platform that makes both compromises unnecessary.**

---

## Core Differentiators

### 1. Privacy Is Structurally Enforced — Not Configured

Most analytics tools treat privacy as a compliance toggle: add a consent banner, configure data retention, hope your legal team approves. This approach fails because the data is still collected first and protected second.

Statflow inverts this. The ingestion layer performs anonymization before any write occurs. Raw IP addresses are hashed with a daily-rotating salt that is never persisted — making cross-day re-identification mathematically impossible, not just policy-prohibited. By the time an event touches the database, there is no personally identifiable information left to protect.

**The outcome:** No consent banner required under GDPR, CCPA, or PECR in default mode — confirmed as the legally correct architecture by both France's CNIL and the UK's ICO (who reviewed Plausible's equivalent model). This is not a workaround. It is the correct engineering approach.

**Why this matters over GA4:** GA4 requires cookies and a consent banner, and loses 20–30% of EU traffic as a result. Statflow captures 100% of traffic because it never touches personal data in the first place.

**Why this matters over Matomo:** Matomo supports a cookieless mode, but it is an opt-in configuration layer on top of a system built to track users. Privacy in Matomo is a setting. Privacy in Statflow is the architecture.

---

### 2. Audience Analytics and Behavioral Analytics Share One Event Model

This is the differentiation that no competitor delivers today.

- **Plausible** knows that 1,200 users arrived from a French Google search today. It cannot tell you where they clicked, which CTA they ignored, or what rage-clicked before they abandoned.
- **Microsoft Clarity** shows you a heatmap of those rage-clicks. It cannot tell you that the users who rage-clicked were French, arrived from paid search, and had a 12% lower conversion rate than organic visitors.
- **PostHog** can correlate behavioral events with funnel steps, but it is not privacy-preserving by default, it is not cookieless, and it requires significant infrastructure to self-host.

Statflow stores every event — pageview, custom event, click-map sample, scroll-depth sample, session frame — in the same ClickHouse schema, under the same anonymous visitor identifier, tied to the same session. This means every behavioral observation is filterable by every audience dimension, and vice versa.

**The question Statflow uniquely answers:** "Show me the heatmap for users who arrived from the April email campaign, on mobile, and did not complete checkout." No other tool in the self-hosted open-source market can answer this without joining data across multiple platforms.

---

### 3. Fully Open Source Under AGPL-3.0 — No Paywalled Features

The open-source analytics market has a dirty secret: the best features are hidden behind paid tiers.

- **Matomo** is GPL-licensed and free at its core. But heatmaps cost €199/year, session recording €149/year, funnels €199/year, A/B testing €249/year — and these are the features that make analytics genuinely valuable. Self-hosters who expect a free, complete tool are disappointed.
- **PostHog** is MIT-licensed. But self-hosting is so complex (ClickHouse + Kafka + PostgreSQL + Redis + multiple services) that PostHog's own team publicly discourages it, steering users toward PostHog Cloud where usage-based pricing applies.
- **Microsoft Clarity** is free but entirely proprietary. You have no access to the source, no ability to self-host, no data export, and your data lives on Microsoft's servers.

Statflow's commitment: **every feature that exists in the product is available to any self-hosted instance, for free, under AGPL-3.0, with no commercial tier carve-out.** Heatmaps, session journeys, funnels, retention, cohorts, e-commerce tracking, email alerts — all of it, in the open-source repository, available to anyone who runs `docker compose up`.

The AGPL-3.0 license ensures that any company building a commercial service on top of Statflow must contribute their changes back. This protects the community while keeping the codebase open.

---

### 4. Self-Hosting That Actually Works

"Self-hostable" is a feature that many analytics tools advertise and few deliver well.

PostHog's self-hosted deployment requires a minimum of 4 GB RAM, familiarity with ClickHouse tuning, Kafka configuration, and a willingness to manage database migrations across a distributed system. PostHog's own documentation acknowledges this complexity and actively recommends their cloud offering instead.

Matomo's PHP + MySQL stack is more approachable, but scales poorly beyond moderate traffic, and the plugin ecosystem introduces dependency management complexity.

Statflow's self-hosting target: **a single `docker compose up` on a $6/month VPS should produce a working analytics installation in under 15 minutes, handling up to 10 million pageviews per month with no additional configuration.** The architecture — Elixir ingestion service + ClickHouse + pre-built UI container — follows the same proven model as Plausible, which has demonstrated this is achievable.

For teams that outgrow a single node, a ClickHouse cluster configuration guide will be available, but the single-node path must remain the primary, well-documented path.

---

### 5. No Consent Banner, No Data Loss, No Legal Risk

This is a business-outcome differentiator, not just a technical one.

In the EU, a typical GA4 implementation loses 20–30% of measurable traffic to consent refusals. In some markets (Germany, France, Scandinavia) this figure is higher. For an e-commerce site doing €1 million in annual revenue, this represents analytically invisible revenue — campaigns you cannot measure, funnels you cannot see, conversion problems you cannot diagnose.

Statflow's cookieless, no-PII architecture means consent is not required. Every visitor is counted. Every funnel step is visible. Every campaign is attributed. Not because Statflow ignores privacy law — but because Statflow's architecture means the law does not require consent in the first place.

This is not a minor advantage. For teams with significant EU traffic, accurate measurement is a direct business capability.

---

## Positioning Map

```
                     High Behavioral Depth
                             │
         Microsoft           │         PostHog
         Clarity             │         (cloud)
         (cloud-only,        │         (complex self-host,
          no audience)       │          not privacy-first)
                             │
─────────────────────────────┼─────────────────────────────
 Cloud-only                  │                  Self-hosted
 / No data ownership         │                  / Data ownership
                             │
         Google Analytics 4  │    *** STATFLOW ***
         (consent required,  │    (cookieless, unified,
          cloud-only)        │     self-hosted, AGPL)
                             │
         Plausible           │
         (privacy-first,     │
          no behavioral)     │
                             │
                     Low Behavioral Depth
```

Statflow occupies the upper-right quadrant — high behavioral depth combined with genuine data ownership and privacy — a space that no current tool inhabits.

---

## The Anti-GA4 Argument (How Statflow Wins Each Objection)

| GA4 user objection | Statflow answer |
|-------------------|----------------|
| "GA4 is free" | Statflow self-hosted is also free. The total cost of GA4 includes the GDPR consent infrastructure, the 20–30% data loss, and the regulatory exposure — all of which have real costs. |
| "GA4 has better data" | GA4's data is incomplete by design in the EU. Statflow captures 100% of traffic without sampling, without consent friction, and without modeled (guessed) conversions. |
| "GA4 integrates with Google Ads" | This integration is valuable if you run Google Ads at scale. Statflow's Stats API makes it possible to send conversion events back to Google Ads via server-side conversion tracking — a documented integration pattern. |
| "GA4 has predictive metrics" | Predictive metrics require Google's ML models trained on cross-site data — fundamentally incompatible with Statflow's privacy model. Retention cohorts, funnel conversion rates, and anomaly detection cover 90% of the actionable use cases that predictive metrics address. |
| "Everyone uses GA4" | Everyone used Universal Analytics until they didn't. The market is actively looking for alternatives, particularly in Europe, healthcare, finance, and public sector — exactly where Statflow's privacy architecture is most valuable. |
| "GA4 has BigQuery export" | Statflow's ClickHouse instance IS the data warehouse. Direct SQL access is available to any self-hosted operator. For cloud deployments, a Parquet export pipeline covers the same use case. |

---

## The Anti-Clarity Argument

Microsoft Clarity is a powerful, free tool for behavioral analytics. It is not a threat to Statflow — it is Statflow's most effective sales tool.

Every team using Clarity has already made the decision that behavioral analytics (heatmaps, session replay) are essential. They are using a free, proprietary, cloud-only tool that:

- Sends all behavioral data to Microsoft's servers.
- Cannot be correlated with audience metrics without jumping to another platform.
- Provides no data export, no API, no audit trail of what Microsoft does with the data.
- Has no EU data residency option.

The Statflow pitch to a Clarity user is simple: "You already believe in behavioral analytics. You should own your data, run it on your infrastructure, and correlate it with your audience metrics — all in one tool."

---

## The Anti-PostHog Argument

PostHog is Statflow's most formidable competitor in the self-hosted open-source space. The differentiation is honest and specific:

| Dimension | PostHog | Statflow |
|-----------|---------|---------|
| Privacy by default | No — requires configuration; uses cookies and cross-site identifiers by default | Yes — cookieless, no PII, no consent banner required |
| Self-hosting complexity | Very high (6+ services, 4 GB RAM minimum, Kafka required) | Low (single compose file, 512 MB RAM minimum) |
| Target user | Product engineers and data engineers | Web publishers, marketing teams, product teams, and engineers |
| Web analytics UX | Secondary ("web analytics" is a sub-module) | Primary (audience metrics is the entry point) |
| Licensing | MIT (core) — increasingly cloud-dependent for new features | AGPL-3.0 — entire feature set in self-hosted tier |
| Heatmaps | Not available | Available from Jalon 3 |
| EU GDPR compliance | Requires careful configuration | Default architecture |

PostHog is the right tool for a product engineering team that can run Kubernetes and does not have EU compliance concerns. Statflow is the right tool for everyone else.

---

## What Statflow Is Not

Clarity of positioning requires honest exclusions:

- **Statflow is not a CDP.** It does not stitch user identities across sessions or devices. Cross-session identity resolution is incompatible with the cookieless privacy model.
- **Statflow is not an A/B testing platform.** Feature flags and experimentation belong to PostHog, Unleash, or GrowthBook. Statflow will document integration patterns with these tools.
- **Statflow is not a CRM or marketing automation tool.** It observes behavior; it does not act on it.
- **Statflow is not a replacement for a data warehouse.** It is an analytics platform that exposes raw data for users who want to build their own data pipelines.

---

## Key Messages by Audience

### For web publishers and indie developers

"Replace Google Analytics today with a tool that needs no consent banner, weighs under 2 KB, and gives you a beautiful, real-time dashboard of everything that matters about your site. Run it on a $6 VPS. Own your data forever."

### For marketing teams

"Stop losing 20–30% of your EU traffic to consent refusals. Statflow is cookieless by design — every visitor is counted, every campaign is attributed, every funnel is visible. No gaps, no modeled estimates, no legal exposure."

### For product teams

"Funnels, retention, cohorts, user journeys, and heatmaps — all in one tool, all correlated in the same event model. Ask questions like 'show me the heatmap for users who dropped off step 3 of the checkout funnel, on mobile, in France.' No other self-hosted tool can answer this."

### For engineering and DevOps teams

"One `docker compose up`. One ClickHouse node. Direct SQL access to your raw event data. AGPL-3.0 with the entire feature set available without a commercial license. No Kafka, no Kubernetes, no vendor negotiations."

### For privacy officers and legal teams

"Cookieless, no PII collected, no data leaves your infrastructure. Architecture validated against the same model confirmed compliant by France's CNIL and the UK ICO. The data subject rights obligations under GDPR are structurally satisfied — not patched on top."

---

*This document is a living strategic reference. Revisit and update it at each milestone launch and whenever a significant competitor shift occurs.*
