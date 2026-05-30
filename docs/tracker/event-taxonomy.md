# Statflow — Event Taxonomy & Wire Format

**Status:** Frozen (normative for the wire format)
**Last updated:** 2026-05-16
**Scope:** `packages/tracker` · ingestion API `POST /api/v1/events`
**Authority:** This document defines the tracker **wire format** (the compact,
short-key representation sent over HTTP). The **canonical event model** and the
authoritative **wire ↔ canonical mapping table** live in
`docs/data-model/event-contract.md` (ADR-0007). Where this document and the
event contract appear to differ, the event contract governs.

---

## 1. Design Principles

Every event shares a **common envelope** that carries event identity, page context,
and device information.  Event-specific fields live in the `props` object.  The
envelope uses **short keys** to minimise payload size over the wire; the ingestion
layer normalises every short key to its canonical long name (see
`event-contract.md §2`). Every optional field is omitted (not null-padded) when absent.

---

## 2. Common Envelope (wire format)

All events **must** include these fields.  The tracker assembles this envelope
automatically; product engineers only supply the event name and `props`.

```jsonc
{
  // ── Event identity ────────────────────────────────────────────────────────
  "eid":   "8f14e45f-ce64-4f1a-9b2d-2c3a4b5c6d7e",  // event_id — UUID v4, idempotency
  "k":     "stk_abcdef1234567890",                  // site_key — public site key
  "e":     "pageview",                              // event_name, see §3 / event-contract §4
  "ts":    1747400000000,                           // client Unix timestamp ms (UTC)
  "seq":   4,                                       // monotonic counter within the session

  // ── Page context ──────────────────────────────────────────────────────────
  "u":     "https://example.com/pricing",   // full URL, stripped of credentials
  "p":     "/pricing",                       // pathname
  "h":     "example.com",                    // hostname
  "r":     "https://google.com/",            // document.referrer (omitted if empty)
  "t":     "Pricing — Acme",                 // document.title at capture time

  // ── Device & viewport ─────────────────────────────────────────────────────
  "vw":  1440,   // viewport width  px
  "vh":  900,    // viewport height px
  "sw":  2560,   // screen width    px
  "sh":  1600,   // screen height   px
  "dpr": 2,      // devicePixelRatio (rounded)

  // ── Network / connection (when the NetworkInformation API is available) ────
  "ct":  "4g",   // effectiveType (omitted if unavailable)

  // ── Tracker meta ──────────────────────────────────────────────────────────
  "tv":  "1.0.0"  // tracker version

  // Event-specific data goes under "props" (custom) and "b" (behavioral) — see §3.
}
```

> **Identity is server-derived.** The wire envelope carries **no `visitor_id` and
> no `session_id`**. Both are computed by the ingestion server from a salted hash
> of the request (IP + User-Agent + Accept-Language); the client never generates,
> stores, or transmits them. See `identity-and-privacy.md` and ADR-0008. The
> `User-Agent` is read by the server from the HTTP header — it is not a wire
> field. The site is identified by the public `k` (`site_key`); there is no
> `pid`/`proj_` identifier.

---

## 3. Event Catalogue

The catalogue below describes each event by its **canonical** name and its
event-specific fields. For readability the per-event examples use the canonical
key `event` and a `properties` block; on the wire these are the short keys `e`
and `props` (custom) / `b` (behavioral) per §2 and the mapping table in
`event-contract.md §2`. The event-name vocabulary is the closed set in
`event-contract.md §4` — `scroll_depth` (not `scroll`), `route_change`, etc.

### 3.1 `pageview`

Fired on initial hard-navigation load and on every SPA route change (see `route_change`
for how SPA navigations are differentiated).

```jsonc
{
  "event": "pageview",
  // ...common envelope...
  "properties": {
    "load_time_ms": 412,   // performance.timing navigationStart → loadEventEnd
                           // omitted for SPA route changes
    "entry_type":  "navigate"  // NavigationEntry type: navigate | reload | back_forward | prerender
  }
}
```

---

### 3.2 `route_change` (SPA)

Fired when a SPA changes route via `history.pushState`, `history.replaceState`, or the
`popstate` event, without a full page reload.  Allows funnel and journey analysis
without conflating SPA soft-navigations with real page loads.

```jsonc
{
  "event": "route_change",
  // ...common envelope (url reflects the NEW URL)...
  "properties": {
    "prev_url":    "https://example.com/home",   // URL before the navigation
    "method":      "pushState",                  // pushState | replaceState | popstate
    "duration_ms": 87                            // time from popstate/pushState to
                                                 // next requestAnimationFrame (paint proxy)
  }
}
```

---

### 3.3 `click`

Fired on every `click` / `pointerup` interaction.  Coordinates are stored relative to
the **document** (not the viewport) so they remain valid after scroll.  Both
absolute-px and percentage coordinates are stored to support heatmap normalisation
across different screen resolutions.

```jsonc
{
  "event": "click",
  // ...common envelope...
  "properties": {
    // ── Target identification ────────────────────────────────────────────────
    "selector":   "main > section.pricing .card:nth-child(2) button.cta",
    // Shortest unique CSS selector, computed client-side (max depth: 5)
    "tag":        "BUTTON",          // nodeName
    "text":       "Get started",     // innerText, truncated to 64 chars, PII-scrubbed
    "href":       "/signup",         // if target or ancestor is <a> (relative preserved)
    "attr_id":    "cta-primary",     // id attribute if present
    "attr_name":  "",                // name attribute if present

    // ── Coordinates ─────────────────────────────────────────────────────────
    "x":   743,     // clientX + scrollX  (document-relative, CSS pixels)
    "y":  2140,     // clientY + scrollY
    "xp":  51.6,   // x / document.body.scrollWidth  × 100  (percentage, 1dp)
    "yp":  38.2,   // y / document.body.scrollHeight × 100

    // ── Interaction modifiers ────────────────────────────────────────────────
    "button":   0,    // MouseEvent.button (0=left,1=mid,2=right)
    "shift":    false,
    "meta":     false,
    "ctrl":     false
  }
}
```

> **Selector algorithm:** walk the DOM upward from the target element; stop at the first
> element with a stable `data-track-id`, `id`, or unique class combination.  Avoid
> `:nth-child` when a deterministic selector exists.  Cap total selector length at 256
> chars.  Strip any value that looks like a UUID, hash, or number (dynamic segments) to
> improve aggregation stability.

---

### 3.4 `rage_click`

Three or more clicks on the same target within 1 000 ms.  Detected client-side by the
click listener accumulating a click buffer per target, keyed by `selector`.  Signals
user frustration or broken interactions.

```jsonc
{
  "event": "rage_click",
  // ...common envelope...
  "properties": {
    "selector":  "button#submit-order",
    "tag":       "BUTTON",
    "text":      "Place order",
    "count":     5,          // number of clicks detected in the window
    "duration_ms": 847,      // ms between first and last click in the burst
    "x":  512,
    "y":  980,
    "xp": 35.6,
    "yp": 58.1
  }
}
```

---

### 3.5 `dead_click`

A click that causes no DOM mutation, no navigation, and no new network request within
300 ms of the event.  Detected by patching a `MutationObserver` + `PerformanceObserver`
fence around each click.  Signals broken or invisible interactive elements.

```jsonc
{
  "event": "dead_click",
  // ...common envelope...
  "properties": {
    "selector": "div.card-overlay",
    "tag":      "DIV",
    "text":     "",
    "x":  200,
    "y":  500,
    "xp": 13.9,
    "yp": 29.7,
    "fence_ms": 300   // observation window used (configurable, default 300)
  }
}
```

---

### 3.6 `scroll_depth`

Fired at configurable depth thresholds (default: 25 %, 50 %, 75 %, 90 %, 100 %) as the
user scrolls the page.  Each threshold fires at most once per page view.  Uses
`IntersectionObserver` on a set of sentinel elements injected at the corresponding
percentage positions of `document.body`, avoiding costly scroll-event throttling.

```jsonc
{
  "event": "scroll_depth",
  // ...common envelope...
  "properties": {
    "depth_pct":    75,          // threshold that was just crossed (integer %)
    "max_pct":      75,          // maximum depth reached so far this page view
    "page_height":  4200,        // document.body.scrollHeight at measurement time
    "time_to_ms":   12400        // ms since pageview / route_change to reaching this depth
  }
}
```

---

### 3.7 Form events

Three distinct events cover the form interaction lifecycle.

#### 3.7.1 `form_focus`

Fired when the user focuses (activates) any form field for the first time on a given
form.  One event per form per page view.

```jsonc
{
  "event": "form_focus",
  // ...common envelope...
  "properties": {
    "form_id":      "checkout-form",      // form id/name attribute, or auto-generated stable key
    "form_action":  "/api/checkout",      // action attribute if present
    "field_name":   "email",              // name attribute of the first focused field
    "field_type":   "email"              // input type attribute
  }
}
```

#### 3.7.2 `form_submit`

Fired on the `submit` event of a `<form>` element (before the default action, so it
fires even on validation failures).

```jsonc
{
  "event": "form_submit",
  // ...common envelope...
  "properties": {
    "form_id":      "checkout-form",
    "form_action":  "/api/checkout",
    "field_count":  8,           // total number of fields in the form
    "filled_count": 7,           // fields with non-empty values at submit time
    "duration_ms":  45200        // ms from form_focus to form_submit
  }
}
```

> **Important:** field values are **never** captured.  Only structural metadata (field
> count, type counts) is recorded.

#### 3.7.3 `form_abandon`

Fired when the user has focused a form but navigates away / closes the page without
submitting.  Detected via `visibilitychange` → `hidden` and the `pagehide` event when a
`form_focus` is in flight with no matching `form_submit`.

```jsonc
{
  "event": "form_abandon",
  // ...common envelope...
  "properties": {
    "form_id":        "checkout-form",
    "form_action":    "/api/checkout",
    "last_field":     "card-number",    // name of the last focused field
    "last_field_type":"tel",
    "filled_count":   5,
    "field_count":    8,
    "duration_ms":    38000             // ms from form_focus to abandon
  }
}
```

---

### 3.8 `engagement`

Measures active engagement time on a page view.  "Active" is defined as: the tab is
visible **and** the user has produced a qualifying interaction (mouse move, keypress,
scroll, or click) within the last 10 s.  Emitted at fixed intervals (default: every
10 s of active engagement) and always on page unload.

```jsonc
{
  "event": "engagement",
  // ...common envelope...
  "properties": {
    "active_ms":   24000,   // cumulative active time since pageview / route_change
    "total_ms":    61000,   // wall-clock time since pageview / route_change
    "intervals":   2,       // number of 10 s engagement intervals completed
    "on_unload":   false    // true when fired from visibilitychange/pagehide
  }
}
```

---

### 3.9 `element_visibility`

Fires when a tracked element (decorated with `data-track-visibility`) enters the
viewport.  Useful for measuring content exposure (e.g., did the user see the pricing
table?).  Powered by `IntersectionObserver` with a 0.5 threshold.

```jsonc
{
  "event": "element_visibility",
  // ...common envelope...
  "properties": {
    "selector":       "[data-track-visibility='pricing-table']",
    "track_id":       "pricing-table",    // value of data-track-visibility
    "visible_ratio":  0.82,               // intersectionRatio at firing time
    "time_to_ms":     8200,               // ms since pageview to first visibility
    "duration_ms":    null                // ms element stayed visible (null = still visible on unload)
  }
}
```

---

### 3.10 `custom`

Escape hatch for product-specific events.  Emitted via the public
`statflow.track(name, props)` API.

```jsonc
{
  "event": "custom",
  // ...common envelope...
  "properties": {
    "name":   "video_play",          // custom event name (max 64 chars, snake_case)
    "props":  {                      // arbitrary flat key/value map (max 32 keys)
      "video_id":   "yt-abc123",     // values: string (max 256 chars), number, boolean
      "duration_s": 183,
      "autoplay":   false
    }
  }
}
```

Constraints enforced client-side:

- `name`: snake_case, 1–64 characters, no PII patterns
- `props`: max 32 top-level keys, max value length 256 chars, no nested objects

---

### 3.11 Web Vitals

Fired once per page view for each Core Web Vital as its value is determined by the
browser.  Uses the `web-vitals` library internals or, in the minified tracker, the raw
`PerformanceObserver` entries to avoid bundling the full `web-vitals` package.

#### 3.11.1 `web_vital_lcp` — Largest Contentful Paint

```jsonc
{
  "event": "web_vital_lcp",
  // ...common envelope...
  "properties": {
    "value_ms":  1840.5,          // LCP value in ms
    "rating":    "good",          // good | needs-improvement | poor  (thresholds: 2500 / 4000)
    "element":   "img.hero-banner",   // CSS selector of the LCP element
    "url":       "https://cdn.example.com/hero.webp",  // resource URL if applicable
    "size":      162000           // element area in px² at LCP time
  }
}
```

#### 3.11.2 `web_vital_cls` — Cumulative Layout Shift

```jsonc
{
  "event": "web_vital_cls",
  // ...common envelope...
  "properties": {
    "value":    0.032,          // CLS score (dimensionless)
    "rating":   "good",         // good | needs-improvement | poor  (thresholds: 0.1 / 0.25)
    "sources":  [               // up to 5 largest contributing shifts
      {
        "selector": "div#ad-banner",
        "fraction": 0.021
      }
    ]
  }
}
```

#### 3.11.3 `web_vital_inp` — Interaction to Next Paint

```jsonc
{
  "event": "web_vital_inp",
  // ...common envelope...
  "properties": {
    "value_ms":  88,          // INP value in ms
    "rating":    "good",      // good | needs-improvement | poor  (thresholds: 200 / 500)
    "target":    "button.add-to-cart",   // element that triggered the interaction
    "type":      "pointerdown"           // interaction type
  }
}
```

---

### 3.12 `js_error`

Captures unhandled JavaScript errors and unhandled promise rejections.  Designed to
cover the most common cases without duplicating a full APM tool.

```jsonc
{
  "event": "js_error",
  // ...common envelope...
  "properties": {
    "type":     "unhandledrejection",   // "error" | "unhandledrejection"
    "message":  "Cannot read properties of undefined (reading 'price')",
    // ↑ truncated to 512 chars
    "source":   "https://example.com/assets/app.a1b2c3.js",
    "lineno":   412,
    "colno":    18,
    "stack":    "TypeError: Cannot read …\n    at getPrice (app.js:412:18)\n…",
    // ↑ first 2 000 chars of the stack trace
    "count":    1   // incremented if identical (message+source+line) error fires again
                    // within the same page view (deduplication window)
  }
}
```

Scrubbing rules applied before transmission:

- Any token matching a UUID, JWT, email address, or credit-card pattern in `message` or
  `stack` is replaced with `[redacted]`.
- `source` URL query strings are stripped.

---

## 4. Batch Envelope

Events are never sent individually.  The transport layer wraps them in the unified
batch envelope — **root key `events`** — defined in `event-contract.md §3`
(see `tracker-design.md §4` for flushing logic):

```jsonc
{
  "events": [
    { "e": "pageview", /* ...full wire event... */ },
    { "e": "click",    /* ...full wire event... */ }
  ],
  "sent_at": 1747400012345,   // Unix ms at the moment the batch is dispatched
  "sdk":     "tracker",
  "sdk_v":   "1.0.0"
}
```

The tracker sends `Content-Type: text/plain` (JSON body) on both the `fetch` and
`sendBeacon` paths; the endpoint parses the body as JSON regardless and also accepts
`application/json` from server-side integrations.

---

## 5. Field Reference Index (wire envelope)

The authoritative wire ↔ canonical mapping is `event-contract.md §2`; this table
is the tracker-side summary.

| Wire key | Canonical name      | Type    | Required | Notes                          |
|----------|---------------------|---------|----------|--------------------------------|
| `eid`    | event_id            | string  | yes      | client UUID v4; idempotency key |
| `k`      | site_key            | string  | yes      | public site key (`stk_…`)      |
| `e`      | event_name          | string  | yes      | see §3 / event-contract §4     |
| `ts`     | timestamp           | number  | yes      | UTC Unix ms (server converts to ISO-8601) |
| `seq`    | seq                 | number  | yes      | monotonic per session          |
| `u`      | url                 | string  | yes      | stripped of credentials        |
| `p`      | pathname            | string  | yes      | path component of `u`          |
| `h`      | hostname            | string  | yes      | host component of `u`          |
| `r`      | referrer            | string  | no       | omitted if empty               |
| `t`      | title               | string  | no       | document.title                 |
| `vw`     | viewport_width      | number  | yes      | CSS px                         |
| `vh`     | viewport_height     | number  | yes      | CSS px                         |
| `sw`     | screen_width        | number  | yes      | CSS px                         |
| `sh`     | screen_height       | number  | yes      | CSS px                         |
| `dpr`    | device_pixel_ratio  | number  | no       | rounded                        |
| `ct`     | connection_type     | string  | no       | from NetworkInformation API    |
| `tv`     | tracker_version     | string  | yes      |                                |
| `props`  | custom_properties   | object  | no       | flat key/value map             |
| `b`      | behavioral          | object  | no       | behavioral signals (§3.3 etc.) |

`visitor_id` / `session_id` are **not** wire fields — they are server-derived
(ADR-0008). `User-Agent` is read from the HTTP header, not sent in the body.

---

## 6. Versioning & Evolution

- **Additive changes** (new optional fields in `properties`) — no version bump required.
- **Breaking changes** (rename, type change, removed required field) — bump event name
  suffix: `click_v2`.  Both versions accepted by ingestion for a deprecation window of
  90 days.
- The `tv` (tracker version) field allows the ingestion layer to apply migration shims
  for older client payloads.
