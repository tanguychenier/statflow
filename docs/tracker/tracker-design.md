# Statflow Tracker — Architecture Design

**Status:** Draft  
**Last updated:** 2026-05-16  
**Package:** `packages/tracker`  
**Target bundle:** core (pageview + SPA + transport) < 2 KB gzipped — a hard CI
gate. The click/heatmap collector is a **separately built, lazily-loaded
`import()` module** (`tracker-heatmap.js`, ≤ 4 KB gzipped); it is never part of
the core bundle and is fetched only when `statflow.loadHeatmap()` is called.

---

## 1. Goals & Non-Goals

### Goals

- Ultra-light, vanilla TypeScript — zero runtime dependencies.
- Cookie-free by design; no localStorage writes; stateless on the client side.
- Works correctly on traditional multi-page sites AND single-page applications without
  any framework-specific adapter.
- Survives page unloads reliably (beacon + keepalive fallback).
- Optional, lazily-loaded heatmap extension that does not inflate the core bundle.
- Designed to be served from the customer's own domain (first-party proxy) to avoid
  ad-blocker interference.

### Non-Goals

- Session replay (full DOM recording) — this is a separate, heavier package.
- A/B testing or feature flags — out of scope for the tracker.
- React/Vue/Svelte wrappers — those live in separate adapter packages.

---

## 2. Module Structure

```
packages/tracker/src/
├── index.ts            # Public API surface + init()
├── core/
│   ├── config.ts       # Configuration types & defaults
│   ├── envelope.ts     # Common event envelope assembly
│   ├── queue.ts        # In-memory event queue + flush scheduler
│   ├── transport.ts    # sendBeacon / fetch keepalive transport
│   └── ids.ts          # per-event event_id (UUID v4) + monotonic per-session seq
├── collectors/
│   ├── pageview.ts     # Initial pageview + Navigation Timing
│   ├── spa.ts          # History API patching for SPA route changes
│   ├── clicks.ts       # click / rage_click / dead_click detection
│   ├── scroll.ts       # IntersectionObserver-based scroll depth
│   ├── forms.ts        # form_focus / form_submit / form_abandon
│   ├── engagement.ts   # Active engagement time tracking
│   ├── visibility.ts   # element_visibility via IntersectionObserver
│   ├── vitals.ts       # LCP / CLS / INP via PerformanceObserver
│   └── errors.ts       # window.onerror + unhandledrejection
└── heatmap/            # Lazily-loaded module (NOT in core bundle)
    ├── index.ts        # Entry point, dynamically imported
    ├── capture.ts      # High-frequency mousemove sampling
    └── render.ts       # Canvas-based heatmap overlay (dev/debug only)
```

The `heatmap/` directory is a **separate tsup entry point**.  It is never imported by
`index.ts`.  The core bundle does not know it exists.

---

## 3. Initialization & Configuration API

### 3.1 Snippet

The loading snippet is designed to be paste-and-forget.  It uses an async script tag
with `defer` so it never blocks rendering:

```html
<script>
  window.statflow=window.statflow||{q:[]};
  window.statflow.track=function(){window.statflow.q.push(arguments)};
</script>
<script src="https://YOUR-DOMAIN/sf/tracker.js" defer></script>
```

The tiny inline portion (< 150 bytes minified) stubs out `statflow.track` with a queue
so calls made before the script loads are replayed after initialization.

### 3.2 `init()` Options

```typescript
interface StatflowConfig {
  /** Public site key (`stk_…`) identifying the site — required.
   *  Public by design (ADR-0009); sent in the event body as `site_key`/`k`.       */
  siteKey: string;

  /** Ingestion endpoint base URL.
   *  Defaults to the script's own origin so the first-party proxy works
   *  automatically. The tracker is always served from the operator's own
   *  instance (or its first-party proxy) — there is no Statflow CDN.              */
  apiBase?: string;            // default: window.location.origin

  /** Path under apiBase for the ingestion endpoint */
  apiPath?: string;            // default: "/api/v1/events"

  /** Maximum events per batch before forcing a flush */
  batchSize?: number;          // default: 20

  /** Maximum ms to wait before flushing a non-full batch */
  batchIntervalMs?: number;    // default: 5_000

  /** Scroll depth thresholds to track (0–100) */
  scrollThresholds?: number[]; // default: [25, 50, 75, 90, 100]

  /** Disable specific collectors */
  disable?: Array<
    | 'pageview' | 'spa' | 'clicks' | 'scroll' | 'forms'
    | 'engagement' | 'visibility' | 'vitals' | 'errors'
  >;

  /** Sanitize / filter events before they are queued.
   *  Return the event to keep it, return null to drop it.
   *  Called synchronously — keep it fast.                */
  beforeSend?: (event: StatflowEvent) => StatflowEvent | null;

  /** Override the script origin used for Content Security Policy nonce injection */
  nonce?: string;

  /** Sample rate 0–1.  0.1 means only 10% of visitors are tracked.
   *  Sampling is evaluated once per session and stored in memory only. */
  sampleRate?: number;         // default: 1
}
```

### 3.3 Public API

```typescript
// Defined on window.statflow after init() runs:
interface StatflowPublic {
  /** Manual page view (useful in some SPA setups) */
  page(): void;

  /** Custom event */
  track(name: string, properties?: Record<string, unknown>): void;

  /** Immediately flush the event queue */
  flush(): Promise<void>;

  /** Stop all collection and detach all listeners */
  destroy(): void;

  /** Dynamically load the heatmap module */
  loadHeatmap(opts?: HeatmapOptions): Promise<void>;
}
```

### 3.4 Initialization Flow

```
HTML parse
  └─ Inline stub runs → window.statflow.q = []
       └─ tracker.js loaded (defer)
            ├─ Parse config from data-* attributes on <script> tag, or
            │   from window.statflowConfig object, or
            │   from statflow('init', { … }) call in the queue
            ├─ Evaluate sampleRate → abort if this visitor is not in sample
            ├─ Check DNT / GPC → honour if configured (see privacy.md)
            ├─ Replay queued statflow.track() calls
            ├─ Register all enabled collectors
            ├─ Fire initial pageview
            └─ Start batch flush timer
```

---

## 4. Event Queue & Batching

### 4.1 In-Memory Queue

Events are pushed into a plain array (`Event[]`).  There is no `localStorage`,
`sessionStorage`, `IndexedDB`, or cookie involved.  If the page is closed before a
flush, the sendBeacon path (§5) handles the drain.

```
push(event)
  │
  ├─ queue.length >= batchSize  →  flush() immediately
  │
  └─ else: let the interval timer flush on next tick
```

### 4.2 Flush Scheduler

A single `setInterval` fires every `batchIntervalMs` (default 5 s).  When fired, if the
queue is non-empty, it calls `flush()`.  The interval is cleared by `destroy()`.

Additionally, `flush()` is called:

- Immediately when `queue.length >= batchSize`.
- On `visibilitychange` → `'hidden'` (covers tab switch and close on most browsers).
- On `pagehide` (iOS Safari, bfcache-aware).

### 4.3 Flush Concurrency

Only one in-flight request is allowed at a time.  If a flush is in progress when
another is triggered, the new flush is debounced to run after the current one resolves.
This prevents duplicate delivery under slow connections.

---

## 5. Transport Layer

### 5.1 Decision Tree

```
flush() called
  │
  ├─ Is page being unloaded? (visibility === 'hidden' || pagehide)
  │     └─ navigator.sendBeacon(url, payload)
  │           ├─ returns true  → done (browser queues delivery)
  │           └─ returns false → fall through to fetch keepalive
  │
  └─ Normal flush
        └─ fetch(url, { method: 'POST', keepalive: true, body: payload })
              ├─ Content-Type: text/plain  (same as the beacon path — removes the
              │   CORS preflight from EVERY ingestion request, not just beacons)
              └─ On network error → retry once after 2 s, then drop (don't accumulate)
```

Both transport paths use `Content-Type: text/plain` with a JSON-encoded body.
This is the single canonical transport content type (ADR-0007): there is no
`application/json` mode for the browser tracker. The endpoint parses the body as
JSON regardless of content type.

### 5.2 Why `text/plain` for Beacon

`navigator.sendBeacon` only avoids a CORS preflight if the content type is one of the
"CORS-safelisted" request-header values (`text/plain`, `application/x-www-form-urlencoded`,
or `multipart/form-data`).  We use `text/plain` with a JSON-stringified body.  The
ingestion endpoint reads the raw body and parses it as JSON regardless of
`Content-Type`.

### 5.3 Retry Policy

Retries are intentionally minimal to keep the implementation tiny:

- One automatic retry after 2 000 ms on network failure (not on 4xx).
- No exponential back-off; no persistent queue.  
  Rationale: a pageview that fails to send is not worth making the tracker complex and
  large.  For high-accuracy requirements, operators can increase `batchIntervalMs` to
  send more frequent, smaller batches with a higher success probability.

### 5.4 Payload Serialisation

```typescript
const body = JSON.stringify({
  events:  queue.splice(0),   // drain the queue atomically (root key `events`)
  sent_at: Date.now(),
  sdk:     'tracker',
  sdk_v:   TRACKER_VERSION,   // replaced at build time by tsup define
});
```

The root key is `events` — the unified batch envelope from `event-contract.md §3`.
A typical 20-event batch is a few kilobytes of compact wire-format JSON. The tracker
does **not** set `Content-Encoding: gzip` and does not rely on the browser
compressing request bodies; it keeps batches small instead. The ingestion endpoint
MAY accept gzip-encoded request bodies from server-side integrations, but that is
not part of the browser-tracker transport contract.

---

## 6. SPA History API Hooks

### 6.1 The Problem

SPA frameworks manipulate the browser history via `history.pushState` and
`history.replaceState` without firing any native browser event that can be observed
from outside the framework.  Only `popstate` has a native event.

### 6.2 Patching Strategy

```typescript
const originalPush    = history.pushState.bind(history);
const originalReplace = history.replaceState.bind(history);

history.pushState = (...args) => {
  originalPush(...args);
  dispatchEvent(new CustomEvent('sf:routechange', {
    detail: { method: 'pushState', prevUrl: location.href }
  }));
};
history.replaceState = (...args) => {
  originalReplace(...args);
  // replaceState is often used for scroll restoration or search-param updates;
  // only emit if the pathname+hash actually changed:
  if (location.pathname + location.hash !== prevPath) {
    dispatchEvent(new CustomEvent('sf:routechange', {
      detail: { method: 'replaceState', prevUrl: location.href }
    }));
  }
};

window.addEventListener('popstate', () => {
  dispatchEvent(new CustomEvent('sf:routechange', {
    detail: { method: 'popstate', prevUrl: prevPath }
  }));
});

window.addEventListener('sf:routechange', (e) => {
  // reset per-page-view collectors (scroll depth, form state, engagement timer)
  // enqueue route_change event
});
```

The `prevPath` variable is updated after each navigation to track the previous URL.

### 6.3 Framework Compatibility

| Framework          | Mechanism         | Works with patch? |
|--------------------|-------------------|-------------------|
| React Router v6+   | history.pushState | yes               |
| Next.js App Router | history.pushState | yes               |
| Vue Router         | history.pushState | yes               |
| Nuxt 3             | history.pushState | yes               |
| SvelteKit          | history.pushState | yes               |
| Hash routing       | hashchange event  | yes (separate listener) |

Hash-based routing fires the native `hashchange` event, which is observed directly
without any patching.

---

## 7. Performance Budget & Enforcement

### 7.1 Budget Targets

| Artifact                    | Budget     | Measurement |
|-----------------------------|------------|-------------|
| `tracker.js` (core, gzip)   | 4 300 B    | `size-limit` |
| `behavior.js` (lazy, gzip)  | 6 000 B    | `size-limit` |
| `tracker-heatmap.js` (gzip) | 4 000 B    | `size-limit` |
| Initialization CPU time     | < 5 ms     | Lighthouse / lab |
| Per-event processing time   | < 0.1 ms   | `performance.now()` assertions in tests |
| Memory footprint (queue)    | < 50 KB    | Chrome DevTools heap snapshot |

> **Core budget rationale.** The original 2 KB target assumed a pageview-only
> core (the Plausible/Fathom class of script). Statflow's core is deliberately
> richer: SPA soft-navigation tracking, a concurrency-safe event queue, a
> retrying `sendBeacon`/`fetch keepalive` transport, DNT/GPC enforcement,
> sampling and idempotency. That machinery alone gzips to ~2.3 KB before any
> collector, so 2 KB is not reachable without dropping reliability features.
> The realistic, measured floor is ~4 KB. We hold the core at a 4 300 B gate and
> push **all** behavioral collectors (clicks, rage/dead clicks, scroll, forms,
> engagement, Web Vitals, errors) into `behavior.js` plus heatmaps into
> `tracker-heatmap.js` — both lazily `import()`-ed only when enabled, so a
> pageview-only deployment downloads just the 4 KB core.

### 7.2 `size-limit` Configuration

```jsonc
// packages/tracker/package.json (size-limit section)
[
  {
    "path":    "dist/tracker.js",
    "limit":   "2 KB",
    "gzip":    true,
    "brotli":  false,
    "import":  "{ init }"
  },
  {
    "path":    "dist/tracker-heatmap.js",
    "limit":   "4 KB",
    "gzip":    true,
    "brotli":  false,
    "import":  "{ HeatmapModule }"
  }
]
```

`size-limit` runs in CI (`pnpm size`) and fails the build if either bundle exceeds its
limit.  This is a hard gate — PRs cannot merge if the budget is broken.

### 7.3 Build Configuration (tsup)

```typescript
// packages/tracker/tsup.config.ts
import { defineConfig } from 'tsup';

export default defineConfig([
  {
    entry:      { tracker: 'src/index.ts' },
    format:     ['iife'],
    globalName: 'statflow',
    target:     'es2017',     // ~97% browser coverage without transpilation bloat
    minify:     true,
    treeshake:  true,
    define: {
      __TRACKER_VERSION__: JSON.stringify(process.env.npm_package_version ?? '0.0.0'),
    },
    esbuildOptions(opts) {
      opts.pure = ['console.log', 'console.debug'];   // strip debug calls in prod
    },
  },
  {
    entry:      { 'tracker-heatmap': 'src/heatmap/index.ts' },
    format:     ['iife'],
    globalName: 'statflowHeatmap',
    target:     'es2017',
    minify:     true,
    treeshake:  true,
  },
]);
```

### 7.4 Techniques for Staying Under 2 KB

1. **No dependencies** — every byte of a third-party library is billable against the
   budget.
2. **Short internal names** — internal variables are minified by esbuild; exported
   symbols use short names by convention (`init`, `track`, `flush`).
3. **Feature flags via dead-code elimination** — collectors that can be disabled at
   build time are wrapped in `if (__ENABLE_FORMS__)` guards; tsup's `define` replaces
   them with `if (true)` or `if (false)` and esbuild removes the dead branch.
4. **PerformanceObserver over polyfills** — the vitals collector uses the raw
   `PerformanceObserver` API instead of bundling the 3 KB `web-vitals` library.  The
   trade-off is that edge cases (back/forward cache restoration, bfcache) require
   explicit handling.
5. **Deferred heatmap** — any feature that requires a larger DOM surface or
   high-frequency event listeners is banished to the dynamic import path.

---

## 8. Heatmap Module (Lazy-Loaded)

### 8.1 Loading

```typescript
// Called by the product team on pages where heatmaps are needed:
statflow.loadHeatmap({ sampleRate: 0.1 });

// Internally:
async function loadHeatmap(opts) {
  const mod = await import(
    /* webpackIgnore: true */
    new URL('/sf/tracker-heatmap.js', config.apiBase).href
  );
  mod.init(opts);
}
```

The `/* webpackIgnore: true */` comment prevents bundlers on the customer side from
trying to inline the module.  The URL is constructed from `config.apiBase` so it
automatically follows the first-party proxy configuration.

### 8.2 What the Heatmap Module Captures

- `mousemove` events sampled at 50 ms intervals (throttled with `requestAnimationFrame`
  alignment), producing `{x, y, xp, yp}` coordinate records identical to the click
  coordinate schema.
- Touch events (`touchmove`, `touchstart`) mapped to the same schema.
- Records are accumulated in a ring buffer (max 2 000 points) and flushed as a single
  `heatmap_batch` event through the same transport as the core tracker.

### 8.3 `heatmap_batch` Event

```jsonc
{
  "event": "heatmap_batch",
  // ...common envelope...
  "properties": {
    "points": [
      { "x": 412, "y": 880, "xp": 28.6, "yp": 52.3, "t": 4200 },
      // ... up to 2 000 points
      // "t" = ms since pageview (relative time for replay ordering)
    ],
    "duration_ms": 18000,   // time window covered by this batch
    "sample_rate": 0.1      // fraction of mousemove events retained
  }
}
```

### 8.4 Privacy Considerations for Heatmaps

- Mouse coordinates are stored as percentages relative to page dimensions, not absolute
  pixels.  This makes them stable across responsive layout changes.
- No keyboard input is ever captured by the heatmap module.
- The heatmap module respects the same DNT / GPC configuration as the core tracker.
- Heatmap collection must be explicitly enabled — it is opt-in, not opt-out.

---

## 9. Collector Lifecycle

Each collector follows the same interface:

```typescript
interface Collector {
  /** Register event listeners / observers */
  mount(config: StatflowConfig, push: (event: PartialEvent) => void): void;

  /** Remove all listeners and observers; reset state */
  unmount(): void;
}
```

`unmount()` is called by `destroy()` and by the SPA route change handler (collectors
are remounted after each navigation to reset per-page-view state such as scroll depth
thresholds and form tracking).

---

## 10. Content Security Policy Compatibility

The tracker must work in strict CSP environments.  Key rules:

- The script tag uses `defer` (not `async`) to remain compatible with nonce-based CSP.
  The `nonce` attribute is set by the server-rendered HTML template.
- No `eval`, no `new Function`, no inline event handlers.
- No dynamic `<script>` injection for the core tracker.
- The heatmap dynamic import resolves to the same origin as the tracker script, so it
  falls under the existing `script-src 'self'` or host allowlist.
- The `connect-src` directive must include the ingestion endpoint origin (which is the
  customer's own domain when using the first-party proxy).

---

## 11. Error Handling

The tracker is designed to be silent in production.  All internal errors are caught and
swallowed; they must never propagate to the host page:

```typescript
function safeCollect(fn: () => void, collectorName: string): void {
  try {
    fn();
  } catch (e) {
    // In development builds only, log to console:
    if (__DEV__) console.warn(`[statflow:${collectorName}]`, e);
    // Never rethrow.
  }
}
```

This pattern wraps all collector `mount()` calls and all event push operations.

---

## 12. Open Questions / Future Work

1. **Queue persistence across unloads** — should a small ring buffer of unsent events be
   held in `sessionStorage` (without PII) to survive a premature close?  Currently the
   sendBeacon path is relied upon exclusively.

2. **Server-Sent Events from the tracker** — could the *tracker* open an SSE
   channel rather than batching? Rejected: it breaks the < 2 KB budget and the
   beacon model. Note this is a separate question from the **dashboard** Realtime
   feature, which *does* use SSE — that SSE stream runs between the dashboard SPA
   and the API (`GET /api/v1/analytics/{site_id}/realtime/stream`), never to or
   from the tracker. The tracker remains a pure batched-`POST` client.

3. **PerformanceObserver `bfcache` restoration** — `pageshow` with `persisted: true`
   should re-fire a `pageview` with an `entry_type: 'back_forward_cache'` property.
   The collector is stubbed but not fully specified yet.

4. **Web Worker transport** — offloading the JSON serialisation and fetch to a Web
   Worker to eliminate any main-thread cost of flushing, at the cost of ≈ 0.5 KB.
