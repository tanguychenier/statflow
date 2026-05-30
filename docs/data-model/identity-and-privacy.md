# Identity and Privacy Model — Statflow

**Status:** Frozen (normative). This document is the **single source of truth**
for `visitor_id` / `session_id` derivation. The tracker privacy spec
(`docs/tracker/privacy.md`) and the event taxonomy reference it and must not
re-specify the algorithm. The decision is recorded in
[ADR-0008](../adr/0008-cookieless-identity-model.md).

---

## 1. Overview

Statflow is cookieless by design. No persistent identifiers are stored in the visitor's browser — no first-party cookies, no localStorage, no IndexedDB, no fingerprint hashes. Despite this, the platform provides meaningful audience metrics (unique visitors, returning visitors, sessions) through a server-side, hash-derived identity model. This document defines how `visitor_id` and `session_id` are derived, the privacy guarantees this approach provides, and its known trade-offs.

---

## 2. No-Cookie Approach

### 2.1 Why no cookies

The standard analytics approach — assigning a persistent UUID in a first-party cookie — has two problems for a privacy-first platform:

1. **Legal friction**: persistent cookies for analytics require informed consent under GDPR (EU), PECR (UK), ePrivacy Directive, and equivalent laws in ~50 jurisdictions. A consent banner is required, which damages UX and typically achieves only 60–80% opt-in rates, biasing your data.
2. **User expectation mismatch**: visitors of a site using Statflow reasonably expect that browsing a public website does not result in persistent cross-session tracking tied to their device.

Statflow's approach produces audience metrics without any client-side storage, making it legally classifiable as **strictly necessary measurement** under most European data protection guidance — no consent banner required. This is the same legal basis claimed by Plausible Analytics, Fathom Analytics, and the CNIL's exemption framework (délibération n°2020-091).

### 2.2 What is not collected

| Data point | Collected? | Reason |
|---|:---:|---|
| IP address (stored) | No | Geo-resolved at ingestion, then discarded |
| User-Agent string (stored) | No | Parsed for device/browser fields, then discarded |
| Cookies (any) | No | Cookieless by design |
| localStorage / sessionStorage | No | — |
| Canvas fingerprint | No | — |
| WebGL fingerprint | No | — |
| Cross-site tracking | No | `visitor_id` is salted per-site |

---

## 3. `visitor_id` Derivation

### 3.1 Algorithm

`visitor_id` is derived on the server at ingestion time using a single keyed hash:

```
visitor_id = HMAC-SHA256(
    key  = daily_salt || site_id,
    data = ip_address || "\n" || user_agent || "\n" || accept_language
)
```

Expressed as a 64-character lowercase hexadecimal string. It is a **single
HMAC** — there is no inner pre-hash.

**Components:**

| Component | Description |
|---|---|
| `daily_salt` | A 256-bit value, **in-memory only, never persisted**, valid for the current UTC day (see §3.2) |
| `site_id` | The UUID of the tracked site, mixed into the HMAC **key**. This is the sole cross-site isolation mechanism: the same visitor on two sites produces different `visitor_id` values. |
| `ip_address` | The visitor's IP address (IPv4 or IPv6 in normalised form) — **never stored**; used only as HMAC input |
| `user_agent` | The full `User-Agent` HTTP header value — **never stored** |
| `accept_language` | The `Accept-Language` HTTP header — reduces collisions for visitors sharing an IP behind CGNAT |

The three data signals are joined with a newline (`\n`) delimiter, which cannot
occur inside any of the values, so the concatenation is unambiguous. `hostname`
is **not** an input: `site_id` already provides per-property separation.

The result is deterministic within a day (for counting returning visitors within a single day) but rotates at the UTC day boundary, making cross-day re-identification computationally infeasible without access to the salt.

### 3.2 Daily Salt — In-Memory, Never Persisted

The daily salt is **never written to Redis, disk, a database, or a backup**.
This honours the roadmap's Jalon-1 acceptance criterion ("in-memory only — never
persisted", "no hash that persists beyond the current UTC day") and keeps the
GDPR position at its strongest. The earlier Redis-with-48h-TTL design is
withdrawn (see ADR-0008).

The salt is obtained as follows:

1. Each ingestion worker derives the salt deterministically:
   `daily_salt = HKDF-SHA256(install_secret, "statflow-daily-salt", utc_date)`.
2. `install_secret` is a high-entropy value set once at install time, supplied
   to each worker via an environment variable / secret mount, and held **only in
   process memory**. `utc_date` is the current `YYYY-MM-DD` in UTC.
3. Because every worker computes the same function of the same inputs, all
   horizontally-scaled workers agree on the salt **with no shared salt store and
   no network round-trip**.
4. At 00:00 UTC the `utc_date` input changes; the previous day's salt can no
   longer be reproduced and is, for all practical purposes, gone. There is no
   grace window — late-arriving events are bucketed by their own event-day date.

**Salt security requirements:**

- The `install_secret` must be treated with the same sensitivity as a signing
  key: stored in a secret manager, never logged, never committed.
- The derived salt itself exists only transiently in worker memory and is never
  exported.

### 3.3 HMAC vs. Simple Hash

SHA-256 alone would be vulnerable to pre-computation attacks: an adversary with a list of known IP+UA combinations could hash them and compare against stored `visitor_id` values to de-anonymise. HMAC-SHA256 prevents this because the adversary would also need the daily salt, which is never persisted and never leaves worker memory.

---

## 4. `session_id` Derivation

A session represents a continuous period of activity with no gap exceeding 30 minutes.

```
session_id = HMAC-SHA256(
    key  = daily_salt || site_id || "session",
    data = ip_address || "\n" || user_agent || "\n" || accept_language
           || "\n" || session_window
)
```

where `session_window` is `floor(unix_seconds / 60)` of the **first event of
the session** — the minute bucket in which the session started.

**One salt, two identifiers.** `session_id` uses the **same `daily_salt`** as
`visitor_id`. Independence between the two identifiers is achieved by appending
the literal domain-separation string `"session"` to the HMAC key, not by
maintaining a second salt on a second rotation schedule. An adversary who
observes both a `visitor_id` and a `session_id` still cannot link them: the keys
differ, so the two HMAC outputs are computationally unrelated. This delivers the
non-correlation property without the operational cost of a second salt store
(see ADR-0008 — the separate-`session_salt`-every-6-hours design is withdrawn).

**Session continuity**: if a visitor returns after more than 30 minutes, the
ingestion layer assigns a new `session_window` (the first event of the new
session falls in a different minute), producing a new `session_id`. This is the
mechanism that separates sessions without cookies. The session boundary is
inferred server-side from event timestamps and inter-event gaps; the client
holds no session state.

---

## 5. Trade-offs and Accuracy Limitations

### 5.1 Returning Visitor Accuracy

The most significant limitation of daily salt rotation is reduced accuracy for **returning visitor counts across days**.

- **Within a single day**: `visitor_id` is fully stable. A visitor who loads 10 pages in a day produces the same `visitor_id` for all 10 events. Unique visitor count for a single day is exact (up to HyperLogLog approximation error in rollup tables).
- **Across days**: midnight salt rotation produces a different `visitor_id` for the same physical visitor on day 2 vs. day 1. A visitor who visits on Monday and Tuesday will be counted as 2 unique visitors in a 7-day report, rather than 1.

**Practical impact**: for high-traffic sites, this causes a systematic over-count of unique visitors in multi-day time ranges. The magnitude depends on the returning visitor rate:

| Returning visitor rate | 7-day unique visitor over-count |
|---|---|
| 5% (typical content site) | ~3–4% |
| 20% (SaaS product) | ~13–15% |
| 40% (highly engaged community) | ~24–26% |

This is a known and accepted trade-off. The over-count is disclosed in the UI as a notice on multi-day unique visitor metrics. For product analytics where identifying individual users matters (logged-in user tracking), the recommended approach is to pass a hashed user ID as a custom property, which can be stable across sessions without storing any PII in Statflow.

### 5.2 CGNAT and Shared IP Environments

Multiple visitors behind the same IP (carrier-grade NAT, office network, university) with identical `User-Agent` strings will produce the same `visitor_id`. This is an under-count of unique visitors that affects all cookieless analytics systems.

Mitigation: the tracker SDK can optionally include a short-lived browser-entropy value (the fractional milliseconds of `performance.now()` at page load, sent as a header) that differentiates visitors on the same IP without constituting a persistent fingerprint. This is opt-in per site and documented as `site_settings.script_variant = 'entropy'`.

### 5.3 VPN and Tor Users

Visitors who change IP between requests (VPN rotation, Tor exit nodes) will produce different `visitor_id` values for each IP, causing under-counting of their page views as a single visitor. This is acceptable: Statflow counts them as distinct anonymous visitors, which is consistent with the privacy model.

### 5.4 IPv6 Normalisation

IPv6 addresses must be normalised before hashing to avoid the same address producing different `visitor_id` values due to representation differences (`2001:db8::1` vs. `2001:0db8:0000:0000:0000:0000:0000:0001`). The ingestion service uses the standard library's IPv6 full-expansion normalisation before feeding the address into HMAC.

---

## 6. Anonymisation Guarantees

Statflow's identity model provides the following guarantees, provided the salt is not compromised:

1. **Non-reversibility**: given a `visitor_id`, it is computationally infeasible to recover the original IP address or User-Agent without brute-forcing the HMAC with the daily salt.

2. **Forward unlinkability**: after salt rotation, past `visitor_id` values cannot be linked to future values for the same physical visitor. An attacker who steals the ClickHouse database cannot correlate Monday's sessions with Tuesday's sessions for the same user.

3. **Cross-site isolation**: `visitor_id` for the same physical visitor differs across sites (due to `site_id` as part of the HMAC key). Statflow operators cannot track a visitor across multiple sites they manage without the visitor's knowledge.

4. **No PII at rest**: IP addresses and User-Agent strings are never written to any persistent store. Geo-resolution is performed in-memory at ingestion. The only data written to ClickHouse is the derived `visitor_id`, the parsed geo fields (country, region, city), and the parsed device fields (device_type, browser, os).

---

## 7. GDPR Compliance Position

Under GDPR, personal data is any information that can identify a natural person "directly or indirectly." The following analysis supports Statflow's position that `visitor_id` as implemented is not personal data under the recital 26 anonymisation standard:

| Factor | Assessment |
|---|---|
| Can `visitor_id` alone identify a person? | No — it is a keyed hash with no reversal path |
| Can `visitor_id` + auxiliary data identify a person? | Not without the daily salt AND the original IP+UA combination |
| Is the salt accessible to the data controller (site operator)? | No — the salt is never persisted; it exists only transiently in ingestion-worker memory and is unrecoverable after the UTC day boundary |
| Does the data controller (site operator) process IP addresses? | No — IP is geo-resolved and discarded before any storage |
| Can the platform operator correlate visitors across days? | No — salt rotation prevents this |

This analysis is consistent with the position taken by the French data protection authority (CNIL) in its 2020 guidance on analytics cookies and by the Norwegian DPA in its evaluation of Plausible Analytics.

**Residual consideration**: an operator who retains the `install_secret` and a
complete request log (IP, User-Agent, Accept-Language) could re-derive a past
day's salt and therefore re-compute that day's `visitor_id` values. This is
strictly weaker exposure than a persisted salt (the salt itself is never at
rest, and request logs SHOULD suppress IP per §9), but self-hosted operators
should suppress IP from logs and seek their own legal counsel on the personal
data classification question.

---

## 8. Data Subject Rights (GDPR Articles 17–22)

Since `visitor_id` is not linked to any identifiable person in Statflow's data model:

- **Right of access (Art. 15)**: not applicable — no personal data is stored per-visitor.
- **Right to erasure (Art. 17)**: for site-level deletion, Statflow supports `DROP PARTITION` on ClickHouse for the affected time range. For a claimed individual, erasure is not feasible because the mapping from a real person to a `visitor_id` does not exist in the system.
- **Right to portability (Art. 20)**: not applicable — no personal data.
- **Right to object (Art. 21)**: implemented via the Do-Not-Track (DNT) HTTP header and a global opt-out mechanism. When the tracker detects `navigator.doNotTrack === '1'`, no event is sent. A JavaScript opt-out API (`window['statflow-opt-out']`) is also provided.

---

## 9. Security Considerations

| Threat | Mitigation |
|---|---|
| Attacker reads ClickHouse data | `visitor_id` values are non-reversible without the salt; the salt is never stored anywhere the database attacker can reach |
| Attacker reads ingestion-worker memory | Exposes the current day's salt only; it becomes unreproducible at the UTC day boundary |
| Attacker obtains the `install_secret` | Can re-derive any day's salt **only if also holding that day's IP/UA/Accept-Language logs**; mitigated by IP suppression in logs and secret-manager storage of `install_secret` |
| Ingestion service compromise | Attacker can derive `visitor_id` for arbitrary inputs; rotate the `install_secret` immediately |
| Replay attack (resubmit event with known visitor_id) | `visitor_id` is computed server-side and never accepted from the client; ingestion validates the `site_key` and the domain allowlist |
| IP address leakage in logs | Ingestion service must be configured to suppress IP from application logs; only the geo result is logged |
