# Statflow — Privacy Model

**Status:** Draft  
**Last updated:** 2026-05-16  
**Scope:** How Statflow identifies visitors and sessions without cookies, which signals
are honoured, what data is never collected, and the GDPR legal basis.

---

## 1. Core Principle

Statflow is designed to provide accurate audience measurement and behavioral analytics
**without creating a persistent identifier for any individual**.  There is no cookie,
no fingerprinting hash stored on the client, no persistent `localStorage` entry, and no
cross-site tracking.  Privacy is enforced by the architecture of the system, not by a
policy document.

---

## 2. `visitor_id` Derivation

### 2.1 Why Not Client-Side ID Generation

Traditional analytics assign a random UUID to the browser via a first-party cookie or
`localStorage` and use that as the visitor identity.  This creates a persistent,
trackable identifier that:

- Requires a consent mechanism under GDPR Article 6 (legitimate interest is
  contentious; consent is the safer legal basis for persistent identifiers).
- Survives across sessions, enabling long-term individual profiling.
- Is shared with the analytics vendor as raw data.

Statflow takes the opposite approach: **the client never generates, stores, or
transmits an identifier**.

### 2.2 Server-Side Salted Hash

`visitor_id` is computed by the **ingestion server** from signals available in every
HTTP request. The **normative algorithm — the exact HMAC inputs, the salt lifecycle,
and the cross-site isolation mechanism — is defined once in
`docs/data-model/identity-and-privacy.md` and recorded in ADR-0008**. This section
only summarises its privacy-relevant properties; it does not re-specify the formula.

In outline: `visitor_id` is a **single `HMAC-SHA256`** keyed on the current day's
in-memory salt combined with `site_id`, over the visitor's IP address, User-Agent,
and Accept-Language. The `daily_salt` is a 256-bit value that lives **only in
ingestion-worker memory** — it is never written to Redis, disk, or a database — and
becomes unreproducible at the UTC day boundary. Once the day rolls over it is
**cryptographically impossible** to reconstruct that day's `visitor_id` values or to
link yesterday's visitor to today's.

### 2.3 Properties of the Derived `visitor_id`

- **Non-persistent across days**: Two visits from the same browser on different calendar
  days produce different `visitor_id` values.  There is no way to correlate them after
  the fact.
- **Non-reconstructable**: Even if an attacker obtained the full event database, they
  cannot reverse the HMAC to recover the original IP address (the salt is gone).
- **Cross-site isolated**: The same physical visitor produces a different `visitor_id`
  on every site. Cross-site isolation is achieved by mixing `site_id` directly into the
  HMAC key — so the same visitor on two sites that both use Statflow cannot be linked
  across those sites. There is no separate per-project salt namespace; `site_id` inside
  the HMAC key is the sole isolation mechanism (ADR-0008).
- **Approximate, not exact**: Two different users behind the same corporate NAT with the
  same browser version will produce the same `visitor_id`.  This is intentional — it
  means the identifier is not suitable for individual tracking, only for audience
  counting.

### 2.4 Implications for Unique Visitor Counting

Because `visitor_id` resets daily, "unique visitor" counts in the Statflow dashboard
represent **unique visitor-days**, not individual humans.  This is clearly labelled in
the UI.  For weekly and monthly unique counts, the dashboard uses HyperLogLog
cardinality estimation over the set of daily `visitor_id` values — no raw identifiers
leave the aggregation layer.

---

## 3. `session_id` Derivation

A session is defined as a continuous sequence of interactions with no more than 30
minutes of inactivity.

`session_id` is derived by the same algorithm family as `visitor_id` — see the
normative definition in `docs/data-model/identity-and-privacy.md §4` and ADR-0008.
It is a single `HMAC-SHA256` over the same request signals plus a
`session_window` (the minute bucket of the session's first event), keyed on the
**same `daily_salt`** with the literal `"session"` appended to the key for
domain separation. There is **one salt**: a separate session salt is not used.

This produces a stable identifier that:

- Is the same for all events within a 30-minute inactivity window.
- Changes when a new session starts (the first-event minute differs).
- Requires no client-side state — the server infers the session boundary from
  event timestamps and inter-event gaps.

---

## 4. What Is Never Collected

This list is enforced both by the tracker code and by the ingestion API's schema
validation.  The API rejects payloads containing these patterns.

| Data category                         | Why excluded                                                    |
|---------------------------------------|-----------------------------------------------------------------|
| Full IP address (stored)              | Used only for `visitor_id` derivation, then discarded          |
| User-Agent string (stored verbatim)   | Coarsened to browser family + major version before storage     |
| Precise geolocation (GPS)             | Geo-enrichment is city/region level only, from IP              |
| Form field values                     | Never captured, even hashed — see §3.7 in event-taxonomy.md   |
| Passwords, payment data               | Scrubbed by content category even if accidentally included     |
| Email addresses                       | Pattern-matched and redacted from error messages and URLs      |
| Cross-site tracking data              | `visitor_id` is project-scoped; no cross-site correlation      |
| Third-party cookie values             | Never read; tracker does not access `document.cookie`          |
| localStorage / sessionStorage content | Never read or written by the tracker                           |
| Clipboard content                     | Never captured                                                 |
| Screenshot / screen recording         | Not part of the tracker (separate session-replay product)      |
| PII in custom event properties        | `beforeSend` hook + server-side PII pattern scanner            |

### 4.1 URL Sanitisation

URLs are sanitised before storage:

- Credentials (`user:pass@host`) are stripped.
- Query parameters matching common PII patterns are redacted:
  `email`, `token`, `key`, `secret`, `password`, `code`, `auth`, `access_token`,
  `id_token`, `refresh_token`, `session`, `ssn`, `dob`.
- Hash fragments are retained (SPA routing uses hashes; they are not PII in this
  context) unless they match PII patterns.

### 4.2 User-Agent Coarsening

The raw `User-Agent` string is used for `visitor_id` hashing but is then reduced to a
structured record before storage:

```json
{
  "browser":  "Chrome",
  "browser_v": "124",
  "os":       "macOS",
  "os_v":     "14",
  "device":   "desktop"
}
```

The raw string is never written to the event database.

---

## 5. Do-Not-Track & Global Privacy Control

### 5.1 Do-Not-Track (DNT)

`navigator.doNotTrack` is checked during tracker initialization.  The behaviour depends
on the `dnt` configuration option:

| `dnt` option | Behaviour                                                          |
|--------------|--------------------------------------------------------------------|
| `'ignore'`   | DNT is ignored (not recommended, but available for sites with a separate consent mechanism). |
| `'disable'`  | (default) If `navigator.doNotTrack === '1'`, the tracker does not initialize; no events are ever queued or sent. |

The default is `'disable'`, the most conservative interpretation. There is no
`'anonymous'` mode: `visitor_id` and `session_id` are server-derived (ADR-0008),
so the client cannot substitute them, and a server-side equivalent was never
specified. Sites choose `'ignore'` or `'disable'` only.

### 5.2 Global Privacy Control (GPC)

GPC (`navigator.globalPrivacyControl`) is a newer, more standardised signal indicating
the user's opt-out of data sale and sharing.  Statflow treats GPC with the same
behaviour as DNT.  Because Statflow does not sell or share data with third parties by
design, GPC does not change the analytics outcome, but it is honoured anyway as a
privacy-respecting signal.

Effective GPC support has been required in California (CPRA) since January 2023.

### 5.3 Precedence

If either DNT or GPC is set and the configured behaviour is `'disable'`, the tracker
halts.  There is no way to override this from the snippet configuration — the user's
stated preference takes precedence over the site operator's configuration.

---

## 6. GDPR Posture

### 6.1 No Consent Banner Required (Typical Deployment)

Under GDPR Recital 30 and the ePrivacy Directive Article 5(3), placing cookies or
accessing stored information on a device requires user consent unless the access is
**strictly necessary** for a service explicitly requested by the user.

Statflow avoids triggering these requirements by:

1. **Not setting cookies** — no `Set-Cookie` header is ever issued by the tracker or
   ingestion endpoint.
2. **Not reading device storage** — `document.cookie`, `localStorage`,
   `sessionStorage`, and `IndexedDB` are never accessed.
3. **Not storing a persistent client-side identifier** — see §2.

The only data processed is data inherent in the HTTP request (IP, User-Agent), which is
processed server-side and immediately hashed.

**Result:** Statflow's data collection in its default configuration falls under the
`Legitimate Interest` legal basis (GDPR Article 6(1)(f)) for the purposes of website
analytics, without requiring a consent banner.

> **Caveat:** This analysis applies to the core event collection.  If the customer
> integrates custom events that capture user-provided data (e.g., a username passed to
> `statflow.track()`), those custom events may require their own legal basis and consent
> mechanism.  The `beforeSend` hook should be used to strip such data.

### 6.2 Data Minimisation (Article 5(1)(c))

Only data that is necessary for the stated analytics purpose is collected:

- Page URLs and titles — for funnel and journey analysis.
- Interaction coordinates — for heatmap analysis.
- Timing data — for performance metrics and engagement measurement.
- Derived (hashed, non-reversible) visitor/session identifiers — for unique visitor
  and session counting.

No profile is built over time for any individual.

### 6.3 Storage Limitation (Article 5(1)(e))

Raw event data (pre-aggregation) is retained for a configurable rolling window.
After this window, raw events are deleted. Aggregated metrics
(daily/weekly/monthly rollups) are retained for up to 24 months.

The retention window is configured **in days**, range **30–730, default 365** —
one unit, one range, one default across the API (`SiteSettings.data_retention_days`),
the `sites.retention_days` column, and the ClickHouse retention worker. The
backstop table-level ClickHouse TTL is 730 days; the per-site value is enforced
by the retention worker (see `clickhouse-schema.sql` header).

### 6.4 Data Subject Rights

Because Statflow does not store data linked to an identifiable individual (the
`visitor_id` is non-reversible and resets daily), it is not possible to fulfil a GDPR
Subject Access Request (SAR) or Right to Erasure request by locating "all data about
person X" — because no data is linked to a person.

This is a feature, not a limitation: the inability to re-identify individuals is what
makes the system privacy-preserving.  Statflow's DPA (Data Processing Agreement)
template explains this to customers.

### 6.5 Data Processing Agreement

Statflow acts as a **Data Processor** on behalf of the customer (the Data Controller).
A standard DPA is available from the dashboard.  Key terms:

- Statflow processes data solely on documented instructions from the customer.
- No sub-processors have access to raw event data except the infrastructure providers
  listed in the DPA (cloud hosting, CDN).
- Data is processed in the EU by default (Frankfurt region).  US and APAC regions are
  available under Standard Contractual Clauses.

---

## 7. Cookieless vs. Cookie-Based: Comparison

| Characteristic                    | Cookie-based (GA4, Mixpanel)       | Statflow (cookieless)              |
|-----------------------------------|------------------------------------|------------------------------------|
| Persistent visitor ID             | Yes — UUID in cookie               | No — daily hash, non-reversible   |
| Cross-session tracking            | Yes (by design)                    | No (by design)                    |
| Consent banner required (EU)      | Yes (ePrivacy Directive)           | No (no device storage accessed)   |
| Individual user profiles          | Yes                                | No                                 |
| Right to erasure complexity       | High (must find and delete by ID)  | Not applicable (no persistent ID) |
| Accuracy under blockers           | Degraded (cookies blocked/cleared) | Unaffected (no client storage)    |
| Accuracy under ITP / ETP          | Degraded (7-day cookie cap)        | Unaffected                        |

---

## 8. Transparency Disclosure

Customers are encouraged (and in some jurisdictions required) to disclose their use of
analytics in their privacy policy.  A suggested paragraph:

> "We use Statflow, a privacy-first analytics platform, to understand how visitors
> interact with this website.  Statflow collects anonymised usage data (page views,
> interactions, and performance metrics).  It does not use cookies, does not track
> individuals across sessions, and does not share data with advertising networks.  The
> data is used solely to improve this website.  No consent is required to collect this
> data because no personal data, as defined by GDPR, is processed."

---

## 9. Open Questions

1. **Eeny-meeny IAB TCF compliance** — Should Statflow offer a TCF v2.2 vendor
   registration to allow CMPs to programmatically disable it?  This would be useful for
   customers who run both Statflow and consent-requiring tools under a single CMP.
   Currently, customers must wire their CMP's `statflow.destroy()` call manually.

2. **GPC scope** — GPC is currently handled identically to DNT (`'disable'`):
   when set, the tracker does not initialise. Because Statflow neither sells nor
   shares data, GPC arguably need not suppress measurement at all; whether a
   future `'ignore'`-equivalent default for GPC specifically is appropriate is
   under discussion. (There is no `'anonymous'` mode — see §5.1.)

3. **IP address as personal data** — Under the CJEU's ruling in *Breyer v.
   Bundesrepublik Deutschland* (C-582/14), a dynamic IP address may be personal data
   when the data controller can legally obtain means to identify the user from it.  The
   ingestion architecture discards the IP immediately after hashing; it is never stored.
   Whether this is sufficient to take the processing outside Art. 4(1) GDPR is a legal
   question rather than a technical one.  We recommend customers seek their own legal
   advice for their jurisdiction.
