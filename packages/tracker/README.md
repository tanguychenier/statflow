# @statflow/tracker

Privacy-first, cookieless browser tracker for the [Statflow](https://statflow.io) analytics platform.

- **< 2 KB gzipped** core bundle
- Zero runtime dependencies
- Cookie-free by design — no `localStorage`, `sessionStorage`, `IndexedDB`, or `document.cookie` access
- DNT / Global Privacy Control (GPC) support built-in
- Works on traditional MPA and SPA (History API + hash routing)
- `navigator.sendBeacon` with `fetch` keepalive fallback for reliable delivery on page unload
- AGPL-3.0-only

---

## Quick Start

Paste the following snippet into your `<head>` (or before `</body>`). The tiny inline portion stubs out `statflow.track` with a queue so calls made before the script loads are replayed after initialization.

```html
<script>
  window.statflow = window.statflow || { q: [] };
  window.statflow.track = function() { window.statflow.q.push(arguments); };
  window.statflowConfig = {
    siteKey: 'stk_YOUR_KEY_HERE'
  };
</script>
<script src="https://YOUR-DOMAIN/sf/tracker.js" defer></script>
```

Replace `stk_YOUR_KEY_HERE` with the public site key from your Statflow dashboard, and `YOUR-DOMAIN` with your own domain. The tracker is always served from your own instance or its first-party proxy — there is no Statflow CDN.

---

## First-Party Proxy (Recommended)

Serving the tracker from your own domain bypasses ad-blockers that target third-party analytics hostnames. Because `apiBase` defaults to `window.location.origin`, the tracker works automatically with no additional configuration as long as:

- The tracker script is served from `/sf/tracker.js` on your domain.
- The ingestion endpoint is reachable at `apiBase + apiPath` (default `/api/v1/events` on the same origin), forwarding to the Statflow ingestion service.

See `docs/tracker/anti-adblock.md` for Caddy, Nginx, Cloudflare Worker, and Vercel configuration snippets.

---

## Configuration Options

All options are passed via `window.statflowConfig` (or to `init()` when using the ESM import).

```typescript
interface StatflowConfig {
  /**
   * Public site key (`stk_…`) — required.
   * Obtain from the Statflow dashboard. Public by design (ADR-0009); it is
   * sent in the event body as `site_key`, never in an Authorization header.
   */
  siteKey: string;

  /**
   * Ingestion endpoint base URL.
   * Defaults to window.location.origin (first-party proxy mode).
   * Override only for cross-domain setups.
   */
  apiBase?: string;

  /**
   * Path under apiBase for the ingestion endpoint.
   * Default: '/api/v1/events'
   */
  apiPath?: string;

  /**
   * Maximum events per batch before forcing an immediate flush.
   * Default: 20
   */
  batchSize?: number;

  /**
   * Maximum milliseconds to wait before flushing a non-full batch.
   * Default: 5000 (5 seconds)
   */
  batchIntervalMs?: number;

  /**
   * Scroll depth thresholds to track (0–100).
   * Default: [25, 50, 75, 90, 100]
   */
  scrollThresholds?: number[];

  /**
   * Disable specific built-in collectors.
   * Available: 'pageview' | 'spa' | 'clicks' | 'scroll' | 'forms' |
   *            'engagement' | 'visibility' | 'vitals' | 'errors'
   */
  disable?: string[];

  /**
   * Sanitize or filter events before they are queued.
   * Return the event to keep it, return null to drop it.
   * Called synchronously — keep it fast.
   */
  beforeSend?: (event: StatflowEvent) => StatflowEvent | null;

  /**
   * Sample rate 0–1. 0.1 means 10% of page loads are tracked.
   * Evaluated once per page load; no persistent state is written.
   * Default: 1 (100%)
   */
  sampleRate?: number;

  /**
   * Do-Not-Track / Global Privacy Control behaviour.
   *   'disable' — do not initialize if DNT or GPC is set (default)
   *   'ignore'  — honour your own consent mechanism; ignore browser signals
   * DNT/GPC are always evaluated unless 'ignore' is chosen. There is no
   * 'anonymous' mode (ADR-0008 §5): visitor/session IDs are server-derived,
   * so the client has nothing to anonymise.
   * Default: 'disable'
   */
  dnt?: 'disable' | 'ignore';
}
```

---

## Public API

After initialization the following methods are available on `window.statflow`:

```typescript
interface StatflowPublic {
  /** Manually fire a pageview — useful for SPA setups that manage their own routing. */
  page(): void;

  /** Send a custom event with an arbitrary flat property map. */
  track(name: string, properties?: Record<string, unknown>): void;

  /** Immediately flush all queued events to the ingestion endpoint. */
  flush(): Promise<void>;

  /** Stop all collection, remove all event listeners, and clear the queue. */
  destroy(): void;
}
```

### Custom Events

```javascript
// Track a custom event
window.statflow.track('video_play', {
  video_id: 'abc123',
  duration_s: 183,
  autoplay: false,
});
```

The custom name becomes the event's `event_name` and the properties become its flat `custom_properties` map (event-contract.md §6). Constraints applied client-side:

- `name`: snake_case, starts with a letter, 1–64 characters, reserved `sf_` prefix rejected
- `properties`: max 20 keys (`[a-z0-9_]{1,50}`), values must be string, finite number, or boolean (no nested objects), total serialised size ≤ 4 KB

---

## Privacy

### What is never collected

- Cookies — never read or written
- `localStorage` / `sessionStorage` / `IndexedDB` — never accessed
- Form field values — only structural metadata (field count, type)
- Email addresses — pattern-matched and redacted from all text and URLs
- Precise geolocation
- Cross-site identifiers

### Visitor identification

`visitor_id` and `session_id` are computed **server-side** from a salted daily HMAC of IP address, User-Agent, and Accept-Language. The salt is rotated at midnight UTC and never persisted — making it cryptographically impossible to correlate visitors across days. The client never generates, stores, or transmits any identifier.

### GDPR

In the default configuration, Statflow does not access device storage and does not create persistent identifiers. This means the ePrivacy Directive Article 5(3) consent requirement does not apply, and collection falls under GDPR Article 6(1)(f) Legitimate Interest. No consent banner is required for standard deployments.

---

## ESM Import (advanced)

For bundled environments that want tree-shaking:

```typescript
import { init } from '@statflow/tracker';

const tracker = init({
  siteKey: 'stk_YOUR_KEY_HERE',
  disable: ['forms', 'vitals'], // disable collectors you don't need
  beforeSend(event) {
    // Strip any custom property that might contain PII
    if (event.properties?.['email']) {
      return null; // drop the event
    }
    return event;
  },
});

// Later, on SPA unmount:
tracker.destroy();
```

---

## Development

All Node/pnpm commands run inside the `frontend` Docker container (never on the host):

```bash
# Install dependencies
docker compose run --rm --workdir /app frontend pnpm install

# Build
docker compose run --rm --workdir /app frontend pnpm --filter @statflow/tracker build

# Check bundle size (must be < 2 KB gzip)
docker compose run --rm --workdir /app frontend pnpm --filter @statflow/tracker size

# Run tests with coverage
docker compose run --rm --workdir /app frontend pnpm --filter @statflow/tracker test:coverage

# Lint
docker compose run --rm --workdir /app frontend pnpm --filter @statflow/tracker lint

# Mutation testing
docker compose run --rm --workdir /app frontend pnpm --filter @statflow/tracker stryker
```

---

## License

AGPL-3.0-only. Copyright © 2026 Tanguy Chénier.
