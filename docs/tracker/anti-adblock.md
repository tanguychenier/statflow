# Statflow — First-Party Proxy Design (Anti-Adblock)

**Status:** Draft
**Last updated:** 2026-05-16
**Scope:** Infrastructure & deployment — how to serve `packages/tracker` and the
ingestion endpoint from the site's own domain.

> **100% local — no Statflow CDN.** Statflow is fully self-hosted: there is no
> `cdn.statflow.io` and no `ingest.statflow.io`. The tracker script and the
> ingestion endpoint are served by the **operator's own Statflow instance**.
> Throughout this document, wherever a proxy example targets `cdn.statflow.io`
> or `ingest.statflow.io`, substitute the operator's own Statflow host (e.g.
> `https://statflow.internal` or whatever host the operator deployed). The
> first-party proxy therefore has two upstreams that are both under the
> operator's control: their own product domain and their own Statflow instance.
> See `docs/architecture.md` ("100% Local Operation").

---

## 1. The Problem

Third-party analytics scripts are blocked by:

- **Brave Browser** — Brave Shields blocks requests to known tracker domains by
  default, with no opt-out for end-users.
- **uBlock Origin / AdBlock Plus** — EasyList and EasyPrivacy maintain crowd-sourced
  blocklists that include the hostnames and URL patterns of all major analytics
  platforms.
- **NextDNS / Pi-hole** — DNS-level blocking of analytics domains.
- **Browser-native protections** — Firefox Enhanced Tracking Protection (ETP), Safari
  ITP, and others.

The failure mode is silent: the tracking script silently fails to load, and no events
are ever sent.  Studies consistently show 20–40 % of privacy-conscious audiences
(typically high-value developer and tech-industry segments) have at least one of these
protections active.

The root cause is **domain-based blocking**: blocklists match on the hostname
(`analytics.yourplatform.com`, `/sf/tracker.js` path patterns, etc.).  The fix is to
serve everything from the **customer's own domain** — the same domain as their product.
Blocklists cannot block `yoursite.com` without breaking the entire site.

---

## 2. Architecture Overview

```
Browser
  │
  │  GET https://app.customer.com/sf/tracker.js         ← same domain
  │  POST https://app.customer.com/api/sf/events        ← same domain
  ▼
Customer's Reverse Proxy / CDN (Caddy, Nginx, Cloudflare Worker, Vercel Rewrites, …)
  │
  │  GET https://cdn.statflow.io/tracker.js             ← proxied, cache 1h
  │  POST https://ingest.statflow.io/api/v1/events      ← proxied, no cache
  ▼
Statflow Infrastructure
```

The customer's server acts as a **transparent proxy**:

1. The tracker script is cached and served from the customer's domain.
2. Event submissions are forwarded to the Statflow ingestion API, with the customer's
   secret ingestion token appended server-side (never exposed to the browser).

---

## 3. What "First-Party" Means Technically

A request is first-party when `document.domain` (or `location.hostname`) matches the
request destination.  Browsers and content blockers treat first-party requests with far
higher trust:

- **No third-party cookie restrictions** — irrelevant for Statflow (we are cookieless),
  but means the script is not sandboxed by ITP/ETP.
- **No domain-based blocklist match** — blocklists cannot enumerate every customer's
  domain; they only know Statflow's own infrastructure hostnames.
- **No mixed-content issues** — the script and events travel over the same TLS
  connection as the rest of the site.

---

## 4. Setup Options

Multiple integration patterns are supported depending on the customer's infrastructure.
All achieve the same outcome; the right choice depends on operational constraints.

### 4.1 Caddy Reverse Proxy (Recommended for Self-Hosted)

```caddy
# Caddyfile snippet — add to the customer's existing Caddyfile

app.customer.com {
  # … existing site config …

  # Serve the tracker script (cached at the proxy level)
  handle /sf/tracker.js {
    reverse_proxy https://cdn.statflow.io {
      header_up Host cdn.statflow.io
      # Cache the script for 1 hour; it changes infrequently
    }
  }

  # Forward event ingestion
  handle /api/sf/events {
    reverse_proxy https://ingest.statflow.io {
      header_up Host                  ingest.statflow.io
      header_up X-Statflow-Token      {env.STATFLOW_INGEST_TOKEN}
      header_up X-Forwarded-For       {remote_host}
      header_up X-Real-IP             {remote_host}
      # Preserve the original IP for server-side visitor_id hashing
    }
  }
}
```

The `STATFLOW_INGEST_TOKEN` environment variable is the project's write-only ingest
key.  It is appended server-side and never sent to the browser.

### 4.2 Nginx

```nginx
# nginx.conf snippet

server {
  server_name app.customer.com;

  # Tracker script — proxy + cache
  location = /sf/tracker.js {
    proxy_pass         https://cdn.statflow.io/tracker.js;
    proxy_set_header   Host cdn.statflow.io;
    proxy_cache_valid  200 1h;
    proxy_hide_header  X-Powered-By;
    add_header         Cache-Control "public, max-age=3600";
  }

  # Event ingestion — proxy, no cache
  location = /api/sf/events {
    proxy_pass         https://ingest.statflow.io/api/v1/events;
    proxy_set_header   Host              ingest.statflow.io;
    proxy_set_header   X-Statflow-Token  $STATFLOW_INGEST_TOKEN;
    proxy_set_header   X-Forwarded-For   $remote_addr;
    proxy_set_header   X-Real-IP         $remote_addr;
    proxy_buffering    off;
  }
}
```

### 4.3 Cloudflare Worker

For customers using Cloudflare in front of their application, a Worker provides the
most flexible proxy without touching their origin server:

```javascript
// statflow-proxy.worker.js

const SCRIPT_URL  = 'https://cdn.statflow.io/tracker.js';
const INGEST_URL  = 'https://ingest.statflow.io/api/v1/events';
const INGEST_TOKEN = STATFLOW_INGEST_TOKEN; // Cloudflare Workers secret

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    if (url.pathname === '/sf/tracker.js') {
      const cached = await caches.default.match(request);
      if (cached) return cached;

      const upstream = await fetch(SCRIPT_URL);
      const response = new Response(upstream.body, upstream);
      response.headers.set('Cache-Control', 'public, max-age=3600');
      await caches.default.put(request, response.clone());
      return response;
    }

    if (url.pathname === '/api/sf/events' && request.method === 'POST') {
      const forwarded = new Request(INGEST_URL, {
        method:  'POST',
        headers: {
          ...Object.fromEntries(request.headers),
          'Host':               'ingest.statflow.io',
          'X-Statflow-Token':   env.STATFLOW_INGEST_TOKEN,
          'X-Forwarded-For':    request.headers.get('CF-Connecting-IP') ?? '',
          'X-Real-IP':          request.headers.get('CF-Connecting-IP') ?? '',
        },
        body: request.body,
      });
      return fetch(forwarded);
    }

    return new Response('Not Found', { status: 404 });
  },
};
```

Route the Worker via `wrangler.toml`:

```toml
[[routes]]
pattern = "app.customer.com/sf/*"
zone_name = "customer.com"

[[routes]]
pattern = "app.customer.com/api/sf/*"
zone_name = "customer.com"
```

### 4.4 Vercel Rewrites

For Next.js / Vercel deployments, `vercel.json` rewrites cover both the script and the
ingestion endpoint:

```json
{
  "rewrites": [
    {
      "source": "/sf/tracker.js",
      "destination": "https://cdn.statflow.io/tracker.js"
    },
    {
      "source": "/api/sf/events",
      "destination": "https://ingest.statflow.io/api/v1/events"
    }
  ]
}
```

Note: Vercel rewrites do not support injecting custom headers (such as the ingest
token) at the edge without a Middleware function or Edge Function.  Use an Edge
Function for production deployments that require token injection.

### 4.5 Next.js `next.config.js` (App Router / Pages Router)

```javascript
// next.config.js
module.exports = {
  async rewrites() {
    return [
      {
        source:      '/sf/tracker.js',
        destination: 'https://cdn.statflow.io/tracker.js',
      },
      {
        source:      '/api/sf/events',
        destination: `https://ingest.statflow.io/api/v1/events`,
      },
    ];
  },
};
```

For token injection, use a Next.js API route (`/api/sf/events/route.ts`) that
forwards the body to the Statflow ingest endpoint with the token added server-side.

---

## 5. Tracker Snippet Configuration for First-Party Mode

When the first-party proxy is active, the tracker script URL and `apiBase` are both set
to the customer's domain:

```html
<!-- Tracker snippet in first-party mode -->
<script>
  window.statflow = window.statflow || { q: [] };
  window.statflow.track = function() { window.statflow.q.push(arguments); };
  window.statflowConfig = {
    siteKey: 'stk_abcdef1234567890',         // public site key (ADR-0009)
    apiBase: 'https://app.customer.com',      // the site's own domain
    apiPath: '/api/sf/events'
  };
</script>
<script src="https://app.customer.com/sf/tracker.js" defer></script>
```

Because `apiBase` defaults to `window.location.origin` (the current page's origin),
the first-party proxy works **automatically with no configuration** as long as the
script is served from `/sf/tracker.js` and the ingestion proxy is at `/api/sf/events`
on the same domain.

---

## 6. IP Address Preservation

The ingestion API uses the client IP address as part of the server-side `visitor_id`
hash (see `privacy.md`).  When the first-party proxy forwards requests, it must
preserve the original client IP via `X-Forwarded-For` or `X-Real-IP`.

The Statflow ingestion service reads headers in the following priority order:

1. `CF-Connecting-IP` (set by Cloudflare)
2. `X-Real-IP`
3. First value in `X-Forwarded-For`
4. Direct connection remote address (fallback — will be the proxy IP, not useful)

Failure to forward the real IP degrades `visitor_id` uniqueness (all visits appear to
come from the proxy server's IP) and breaks geo-location enrichment.

---

## 7. Security Considerations

### 7.1 The Ingestion Credential — Public Site Key, No Secret Token

There is **no secret ingestion token.** The site is identified by the public
`site_key` (`stk_…`) carried in the event payload body — see ADR-0009 and
`docs/api/README.md §2.3`. The first-party proxy is a **transparent
pass-through**: it forwards the request body unchanged, including the `site_key`
already present in it, and injects **no credential**.

Any reference in this document to a secret `STATFLOW_INGEST_TOKEN` or an
`X-Statflow-Token` header is **superseded by this section**: the proxy
configuration examples above should be read as forwarding `X-Forwarded-For` /
`X-Real-IP` for accurate geo-resolution only. Operators do not need to provision
or rotate an ingest token.

The proxy's value is **ad-blocker evasion** (the request goes to the site's own
domain) and an **accurate client IP** (`X-Forwarded-For` from the proxy), not
credential secrecy. The `site_key` is public by design — embedding it in
client-side JavaScript is expected and safe.

### 7.2 Request Forgery

The ingestion endpoint authenticates the **site**, not the individual end-user,
via the public `site_key`. Because the key is public, a malicious actor could
craft events for the proxy URL (`/api/sf/events`). Mitigations:

- The `site_key` is validated against the site's **domain allowlist**
  (`site_settings.allowed_domains`) — the request `Origin` must match.
- The Statflow ingestion layer enforces **per-site rate limits**.
- Anomaly detection flags implausible event volumes from a single IP.

### 7.3 CORS for the Ingestion Endpoint

Because the tracker sends to the same origin as the page (first-party proxy),
**no CORS preflight occurs**. Even on the direct, cross-origin path, the tracker
uses `Content-Type: text/plain`, which is CORS-safelisted and triggers no
preflight (see `event-contract.md §3`).

---

## 8. Trade-Offs

Both modes serve from the operator's own Statflow instance (there is no Statflow
CDN). The choice is whether to additionally proxy that instance under the site's
own product domain:

| Factor              | First-Party Proxy                       | Direct Load (site → operator's instance) |
|---------------------|-----------------------------------------|-------------------------------------------|
| Ad-blocker bypass   | Effective (same domain as the product)  | Blocked if the instance host is on a blocklist |
| Setup complexity    | Requires proxy config                   | Just a script tag                         |
| Latency             | +1 network hop (proxied)                | Direct                                    |
| IP preservation     | Requires `X-Forwarded-For` forwarding   | Automatic                                 |
| Script caching      | Operator controls TTL at the proxy      | Served by the operator's instance         |
| Credential          | Public `site_key` in body (no secret)   | Public `site_key` in body (no secret)     |
| Script updates      | Delayed by cache TTL (1h max)           | Immediate from the operator's instance    |

For most production deployments, the increased data accuracy from bypassing blockers
far outweighs the operational overhead of the proxy configuration.

---

## 9. Monitoring the Proxy Health

The Statflow dashboard displays a "Data Collection Health" indicator that shows the
ratio of sessions where the tracker script loaded successfully versus sessions where it
was blocked.  This ratio is inferred from:

1. A lightweight `<img>` ping on page load (before the tracker script loads) that fires
   to a different endpoint — if this ping arrives but no corresponding tracker events
   follow within 5 s, the tracker load is inferred to have been blocked.
2. Browser-reported script load errors captured by the snippet's `onerror` handler.

If the health indicator drops below 90 %, the dashboard surfaces a "Configure
first-party proxy" prompt with a link to the relevant setup guide for the detected
infrastructure type (based on response headers from the customer's domain).
