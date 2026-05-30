# 0008 — Cookieless Identity Model

Date: 2026-05-16

## Status

Accepted

Supersedes the `visitor_id`/`session_id` derivation previously described in
`docs/tracker/privacy.md §2–3` (which omitted `site_id` and double-hashed) and
the salt-storage design in `docs/data-model/identity-and-privacy.md §3.2`
(Redis, 48-hour TTL).

## Context

The cookieless identity model is Statflow's central privacy guarantee. Three
documents defined it and contradicted each other on every parameter:

- **HMAC inputs.** `identity-and-privacy.md` keyed the HMAC on
  `daily_salt || site_id` over `ip || user_agent || hostname`; `privacy.md`
  keyed on `daily_salt` alone over an inner `SHA256(ip:ua:accept_language)`.
- **Cross-site isolation.** One document attributed isolation to `site_id` in
  the HMAC key; the other to a per-project salt namespace that does not exist
  in its own formula.
- **Salt storage.** `identity-and-privacy.md`: Redis, 48-hour TTL.
  `privacy.md`: in-memory only, 24-hour lifetime. The roadmap (feature 1.1 and
  the Jalon-1 acceptance criteria) requires "in-memory only — never persisted"
  and "no hash that persists beyond the current UTC day."
- **Session salt.** `identity-and-privacy.md`: a separate `session_salt`
  rotated every 6 hours. `privacy.md`: the same `daily_salt`.
- **Client vs server.** The tracker taxonomy listed `vid`/`sid` as required
  client fields; both privacy documents insist the client never generates them;
  OpenAPI allowed the client to optionally supply `visitor_id`.

The Redis-with-TTL design also directly weakens the GDPR position the whole
privacy posture rests on (a persisted salt is a "reasonable means" of
linkability) and contradicts the roadmap's hard acceptance criterion.

## Decision

There is **one identity algorithm**, defined once in
`docs/data-model/identity-and-privacy.md` and referenced — never re-specified —
everywhere else.

1. **`visitor_id`.**

   ```
   visitor_id = HMAC-SHA256(
       key  = daily_salt || site_id,
       data = ip_address || "\n" || user_agent || "\n" || accept_language
   )
   ```

   - The key mixes `site_id` in, so the same physical visitor produces a
     different `visitor_id` on every site. This — and only this — is the
     cross-site isolation mechanism. The "per-project salt namespace" claim is
     deleted.
   - The data is the three request-inherent signals joined by a newline
     delimiter (an unambiguous separator that cannot occur inside the values).
     `accept_language` is included because it reduces collisions behind CGNAT;
     `hostname` is **not** included (`site_id` already provides per-property
     separation, and `hostname` is redundant with it).
   - It is a **single HMAC**. The inner `SHA256` from `privacy.md` is removed —
     it added no security and broke determinism between the two specs.
   - Output: 64-character lowercase hexadecimal.

2. **`session_id`.**

   ```
   session_id = HMAC-SHA256(
       key  = daily_salt || site_id || "session",
       data = ip_address || "\n" || user_agent || "\n" || accept_language
              || "\n" || session_window
   )
   ```

   - `session_window = floor(unix_seconds / 60)` of the **first event of the
     session**, i.e. the minute bucket in which the session started. All events
     within a 30-minute inactivity window resolve to the same `session_id`.
   - There is **one salt**, the `daily_salt`. Session and visitor identifiers
     are kept independent by appending the literal domain-separation string
     `"session"` to the HMAC key, not by maintaining a second salt on a second
     rotation schedule. This achieves the "cannot be cross-correlated" property
     from `identity-and-privacy.md §4` without the operational cost and the
     extra Redis-style store the separate-salt design implied.

3. **Salt storage — in-memory, shared, never persisted.**

   - The `daily_salt` is a 256-bit value generated at the first ingestion
     request on or after 00:00 UTC.
   - It lives **only in process memory**. It is never written to Redis, disk, a
     database, or a backup. This honours the roadmap acceptance criterion
     verbatim and keeps the GDPR position at its strongest.
   - Multi-worker coordination: all ingestion workers within one instance share
     the salt via the FrankenPHP worker-mode shared memory / a single
     salt-holder service in the process group. Horizontal scaling beyond one
     instance derives the salt deterministically from a **per-deployment
     install secret** plus the current UTC date:
     `daily_salt = HKDF-SHA256(install_secret, "statflow-daily-salt", utc_date)`.
     The install secret is set once at install time and held in memory by each
     worker (sourced from an environment variable / secret mount); the *salt
     itself* is still never persisted, and it still rotates and becomes
     unrecoverable at the UTC day boundary because the date input changes.
   - **Lifetime: the current UTC day.** At 00:00 UTC the date input changes and
     the previous day's salt can no longer be reproduced. There is no 48-hour
     grace window; late-arriving events from the previous day are bucketed by
     their own event timestamp's date for storage, and any cross-midnight
     re-derivation simply uses the event-day date.

4. **Identity is server-only.** The tracker never generates, stores, or
   transmits `visitor_id` or `session_id`. They are absent from the wire format
   and from the API request schema (see ADR-0007). The OpenAPI behaviour of
   accepting a client-supplied `visitor_id` is removed — accepting it was a
   replay/identity-spoofing hole.

5. **DNT/GPC `disable` is the only non-tracking mode.** When DNT or GPC is set
   and the site is configured for the default `disable` behaviour, the tracker
   does not initialise and sends nothing. The `anonymous` mode previously
   described in `privacy.md §5.1` is **removed**: it is unimplementable
   client-side (the client has no `visitor_id` to substitute) and the
   server-side equivalent was never specified. Sites choose `ignore` or
   `disable` only.

## Consequences

- The privacy guarantee is now consistent across all specs and matches the
  roadmap's hard constraints. The GDPR analysis in `identity-and-privacy.md §7`
  no longer has to concede a persisted-salt weakness.
- Cross-site isolation has exactly one mechanism (`site_id` in the HMAC key),
  which is real and testable.
- Horizontal scaling works without a shared salt *store*: every worker derives
  the same salt from `(install_secret, utc_date)` with no network round-trip
  and nothing persisted.
- Returning-visitor counts across days remain approximate (daily rotation), as
  before; this is an accepted, documented trade-off.
- The session finalizer and `sessions_mv` design in the ClickHouse layer is
  unaffected: it still derives session boundaries from event timestamps.
- One small risk: deriving the salt from an install secret means an operator
  who keeps the install secret *and* a full request log could replay a day's
  salt. This is strictly weaker exposure than the rejected Redis design (the
  salt is never at rest) and is documented in `identity-and-privacy.md §9`.
