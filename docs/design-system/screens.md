# Statflow — Screen Designs & UX Specifications

> Version 1.0  
> Breakpoints: mobile < 768px · tablet 768–1023px · desktop ≥ 1024px

---

## Global Layout Shell

All authenticated screens share a consistent shell:

```
┌─────────────────────────────────────────────────────────────────┐
│  TOPBAR (56px)                                                  │
│  [≡]  statflow · mysite.com ▾     [Dec 1–31 ▾]  [⌘K]  [🔔] [●]│
├──────┬──────────────────────────────────────────────────────────┤
│      │                                                          │
│  S   │                                                          │
│  I   │    CONTENT AREA (scrollable)                            │
│  D   │                                                          │
│  E   │                                                          │
│  B   │                                                          │
│  A   │                                                          │
│  R   │                                                          │
│      │                                                          │
├──────┘                                                          │
└─────────────────────────────────────────────────────────────────┘
```

**Responsive shell behavior:**

- Desktop (≥ 1024px): Sidebar persistent, 220px expanded / 56px collapsed
- Tablet (768–1023px): Sidebar collapsed by default (56px), expands on toggle
- Mobile (< 768px): Sidebar hidden; hamburger in topbar opens a full-height left drawer

---

## Screen 1 — Overview Dashboard

**Route:** `/`  
**Purpose:** At-a-glance health of the tracked property over the selected period.

### 1.1 Layout (desktop)

```
┌──────────────────────────────────────────────────────────────────────┐
│  TOPBAR                                                              │
│  Overview    [Dec 1–31, 2024 ▾]  [Compare: prev period ▾]  [Export]│
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │
│  │ Sessions │ │  Users   │ │Pageviews │ │Bounce %  │ │Avg. Dur. │  │
│  │ 84,203   │ │ 61,412   │ │ 214,880  │ │  42.1%   │ │  2m 04s  │  │
│  │▲ 12.4%   │ │▲  8.1%   │ │▲  9.7%   │ │▼  3.2pp  │ │▲  0m 08s │  │
│  │ sparkline│ │ sparkline│ │ sparkline│ │ sparkline│ │ sparkline│  │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘  │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │ Sessions over time                        [Daily ▾]  [···]    │  │
│  │                                                                │  │
│  │  █  area line chart, dual series (current + comparison)       │  │
│  │     X-axis: dates, Y-axis: sessions, zoom brush at bottom     │  │
│  │                                                                │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ┌────────────────────────┐  ┌────────────────────────────────────┐  │
│  │ Top Pages              │  │ Traffic Sources                    │  │
│  │ ─────────────────────  │  │                                    │  │
│  │ /pricing      12,043   │  │  ●Direct      38%  ████████░░      │  │
│  │ /docs          9,812   │  │  ●Organic     31%  ██████░░░░      │  │
│  │ /blog/post-1   7,204   │  │  ●Referral    18%  ████░░░░░░      │  │
│  │ /              6,991   │  │  ●Social       9%  ██░░░░░░░░      │  │
│  │ /pricing/pro   4,230   │  │  ●Email        4%  █░░░░░░░░░      │  │
│  │         [View all →]   │  │              [View all →]          │  │
│  └────────────────────────┘  └────────────────────────────────────┘  │
│                                                                      │
│  ┌────────────────────────┐  ┌────────────────────────────────────┐  │
│  │ Devices                │  │ Geography                          │  │
│  │  donut chart           │  │  choropleth map (world)            │  │
│  │  Desktop 62%           │  │  top countries list below          │  │
│  │  Mobile  31%           │  │                                    │  │
│  │  Tablet   7%           │  │                                    │  │
│  └────────────────────────┘  └────────────────────────────────────┘  │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

### 1.2 Key Interactions

- **Metric card click:** opens a detail panel (right drawer, 320px) with a full-size time series for that metric
- **Date range change (topbar):** triggers a global query invalidation; all charts reload with skeleton states
- **Compare toggle:** adds a dashed secondary line to time-series charts and a delta column to all tables
- **Chart zoom brush:** dragging the brush narrows the visible date range but does NOT change the global date filter — it's a view-only zoom
- **Export button:** downloads all visible data as a CSV; opens a small modal to pick which tables/charts to include
- **Metric card overflow menu (···):** options: "View details", "Add to custom dashboard", "Copy link"

### 1.3 Responsive

- **Tablet:** metric cards wrap to 3-column grid; Top Pages + Sources stack vertically
- **Mobile:** single-column layout; time-series chart collapses to a smaller height (200px); metric cards are 2-per-row

---

## Screen 2 — Behavior: Heatmaps & Click Maps

**Route:** `/behaviour/heatmaps`  
**Purpose:** Visual representation of where users click, move, and how far they scroll on a specific page.

### 2.1 Layout

```
┌──────────────────────────────────────────────────────────────────────┐
│  Heatmaps     [Page: /pricing ▾]  [Dec 1–31 ▾]  [Desktop ▾]        │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─ View type ─────────────────────────────────┐                    │
│  │ [Clicks] [Scroll] [Movement] [Rage Clicks]  │                    │
│  └─────────────────────────────────────────────┘                    │
│                                                                      │
│  ┌─────────────────────────────────────┐  ┌────────────────────────┐│
│  │                                     │  │  STATS PANEL           ││
│  │   HEATMAP PREVIEW AREA              │  │                        ││
│  │                                     │  │  Total clicks: 8,203   ││
│  │   [iframe / screenshot of page      │  │  Unique users:   4,012 ││
│  │    with canvas overlay rendered     │  │  Sample size:    6,841 ││
│  │    on top — heat gradient from      │  │                        ││
│  │    cool (blue) → warm (red)]        │  │  ─── Top Elements ───  ││
│  │                                     │  │  CTA button    38%     ││
│  │   Intensity slider: [────●───]      │  │  Pricing table 21%     ││
│  │   Radius slider:    [──●─────]      │  │  Nav links     14%     ││
│  │                                     │  │  Hero image     8%     ││
│  │                                     │  │                        ││
│  │   [Open in fullscreen ↗]            │  │  ─── Scroll depth ─── ││
│  │                                     │  │  100%: ████████  92%   ││
│  └─────────────────────────────────────┘  │   75%: ██████░░  71%   ││
│                                           │   50%: █████░░░  55%   ││
│                                           │   25%: ████░░░░  38%   ││
│                                           └────────────────────────┘│
│                                                                      │
│  ┌─ Page selector ──────────────────────────────────────────────────┐│
│  │  [Search pages...]   Sorted by: [Most clicks ▾]                 ││
│  │  /pricing  8,203 clicks  ████████                               ││
│  │  /         6,991 clicks  ███████                                ││
│  │  /docs     4,812 clicks  ████░                                  ││
│  └──────────────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────────────┘
```

### 2.2 Key Interactions

- **View type tabs (Clicks / Scroll / Movement / Rage Clicks):** switches the overlay data; smooth re-render of the canvas layer
- **Page selector:** selecting a different page re-fetches and re-renders the screenshot + overlay
- **Device toggle (Desktop / Tablet / Mobile):** switches the screenshot viewport and re-renders overlay at that device's recorded coordinates
- **Intensity / Radius sliders:** client-side recalculation — no new API call needed
- **Fullscreen mode:** heatmap expands to fill the viewport; stat panel collapses to a floating sidebar that can be toggled

### 2.3 Responsive

- On mobile the stats panel moves below the heatmap preview
- Heatmap preview is scrollable within its container at all breakpoints

---

## Screen 3 — Funnels

**Route:** `/funnels`  
**Purpose:** Define multi-step conversion funnels and measure drop-off at each step.

### 3.1 Funnel List View

```
┌──────────────────────────────────────────────────────────────────────┐
│  Funnels                                        [+ New Funnel]       │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │ Checkout Funnel       Dec 1–31      4 steps    12.3% overall  │   │
│  │ Last edited: 3 days ago                      [Edit] [Delete] │   │
│  └──────────────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │ Signup Funnel         Dec 1–31      3 steps    28.7% overall  │   │
│  │ Last edited: 1 week ago                      [Edit] [Delete] │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  No more funnels. [+ Create your first funnel]                      │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

### 3.2 Funnel Detail View

```
┌──────────────────────────────────────────────────────────────────────┐
│  ← Funnels  /  Checkout Funnel   [Dec 1–31 ▾]  [Edit funnel]        │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Overall conversion: 12.3%     Total entered: 8,402 sessions        │
│  Median time to convert: 4m 32s                                     │
│                                                                      │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │ FUNNEL CHART                                                  │   │
│  │                                                               │   │
│  │  Step 1: View product     8,402  100.0%                       │   │
│  │  ████████████████████████████████████████                     │   │
│  │                           ▼ 68.2% continued  ▼ 31.8% dropped │   │
│  │  Step 2: Add to cart      5,730   68.2%                       │   │
│  │  ████████████████████████████                                 │   │
│  │                           ▼ 52.1% continued  ▼ 47.9% dropped │   │
│  │  Step 3: Begin checkout   2,986   35.5%                       │   │
│  │  ████████████████                                             │   │
│  │                           ▼ 34.7% continued  ▼ 65.3% dropped │   │
│  │  Step 4: Purchase         1,036   12.3%                       │   │
│  │  ██████                                                       │   │
│  │                                                               │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  ┌─ Breakdown ──────────────────────────────────────────────────┐   │
│  │ Group by: [Device ▾]   [Country ▾]   [Source ▾]              │   │
│  │ Stacked bar chart showing conversion per group               │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  ┌─ Trend ──────────────────────────────────────────────────────┐   │
│  │ Conversion rate over time (line chart, per step optional)    │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

### 3.3 Funnel Builder (Modal)

```
┌─────────────────────────────────────────────────────────────────┐
│  New Funnel                                               [×]   │
├─────────────────────────────────────────────────────────────────┤
│  Funnel name:  [Checkout Funnel                        ]        │
│                                                                 │
│  Steps:                                                         │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 1  [Event type ▾]  [Event name / URL]  [+ Conditions]  × │   │
│  └──────────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 2  [Event type ▾]  [Event name / URL]  [+ Conditions]  × │   │
│  └──────────────────────────────────────────────────────────┘   │
│  [+ Add step]                                                   │
│                                                                 │
│  Conversion window:  [30 days ▾]                               │
│  Count: [Unique users ▾]                                       │
│                                                                 │
│                                      [Cancel]  [Save funnel]   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Screen 4 — Realtime

**Route:** `/realtime`  
**Purpose:** Live view of active users and their current activity.

### 4.1 Layout

```
┌──────────────────────────────────────────────────────────────────────┐
│  Realtime          ● Live — updated 2s ago                          │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────┐  ┌──────┐  ┌──────┐  ┌──────┐                           │
│  │  247 │  │  /pr.│  │  18  │  │  31% │                           │
│  │Active│  │ top  │  │ evt/m│  │ mbl  │                           │
│  │users │  │ page │  │      │  │      │                           │
│  └──────┘  └──────┘  └──────┘  └──────┘                           │
│                                                                      │
│  ┌───────────────────────────────────────────┐  ┌─────────────────┐│
│  │  Active users — 30-min trend              │  │ Countries       ││
│  │                                           │  │ 🇺🇸 US  82      ││
│  │  [animated pulse bar chart                │  │ 🇩🇪 DE  41      ││
│  │   x-axis = 30 1-min buckets               │  │ 🇫🇷 FR  38      ││
│  │   y-axis = active user count              │  │ 🇬🇧 GB  29      ││
│  │   rightmost bar pulses gently]            │  │ 🇧🇷 BR  18      ││
│  │                                           │  │ ...more         ││
│  └───────────────────────────────────────────┘  └─────────────────┘│
│                                                                      │
│  ┌───────────────────────────────────────────┐  ┌─────────────────┐│
│  │  Top active pages                         │  │ Top referrers   ││
│  │  /pricing            82  ███████          │  │ google.com  104 ││
│  │  /                   61  █████            │  │ direct       89 ││
│  │  /docs               44  ████             │  │ twitter.com  21 ││
│  │  /blog/post-1        28  ██               │  │ ...more         ││
│  └───────────────────────────────────────────┘  └─────────────────┘│
│                                                                      │
│  ┌─ Live event stream ──────────────────────────────────────────────┐│
│  │  [Pause ⏸]                                                      ││
│  │  12:04:38  page_view    /pricing      Firefox/Win    🇫🇷 FR      ││
│  │  12:04:37  click        #cta-button   Chrome/Mac     🇺🇸 US      ││
│  │  12:04:37  page_view    /docs/api     Safari/iOS     🇬🇧 GB      ││
│  │  12:04:36  conversion   checkout      Chrome/Win     🇩🇪 DE      ││
│  │  ...streaming, newest at top, max 200 rows visible              ││
│  └──────────────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────────────┘
```

### 4.2 Key Interactions

- **Transport:** the screen subscribes to the Server-Sent Events endpoint
  `GET /api/v1/analytics/{site_id}/realtime/stream` (`text/event-stream`). The
  `stats` event arrives roughly every 2 seconds; each `event` event appends one
  row to the live stream panel. There is no polling.
- **Active visitor window:** the "Active users" count and all "current" panels
  use the canonical realtime window — **the last 5 minutes**. The "Active users"
  bar chart shows a 30-minute *trend* for historical context; only the 5-minute
  count is the live KPI. (One window for the KPI everywhere: API, roadmap, and
  ClickHouse all use 5 minutes.)
- **Pause button:** freezes the event stream scroll; data continues to buffer; "X new events" badge appears
- **Active user count:** large number pulses with a subtle glow on update
- **Top pages list:** rows reorder smoothly using FLIP animation when rankings change

### 4.3 Responsive

Mobile simplifies to a single-column stacked layout; the event stream is capped at 100 rows.

---

## Screen 5 — Pages & Sources

**Route:** `/pages-sources`  
**Purpose:** Detailed breakdown of page performance and traffic acquisition.

### 5.1 Layout

```
┌──────────────────────────────────────────────────────────────────────┐
│  Pages & Sources     [Dec 1–31 ▾]    Filters: [+ Add filter]        │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  [Pages] [Entry pages] [Exit pages] [Sources] [Referrers] [UTM]    │
│  ─────── (active underline tab)                                     │
│                                                                      │
│  [Search /path...]   [Metric: Sessions ▾]   [Export CSV]           │
│                                                                      │
│  ┌─ Data Table ──────────────────────────────────────────────────┐  │
│  │  Page ↕         Sessions ↕  Bounce % ↕  Avg dur. ↕  Conv. ↕  │  │
│  ├───────────────────────────────────────────────────────────────┤  │
│  │  /pricing       12,043      42.1%       1m 24s       3.2%     │  │
│  │  /docs           9,812      28.4%       3m 01s       0.8%     │  │
│  │  /blog/post-1    7,204      55.2%       1m 12s       0.4%     │  │
│  │  ...                                                           │  │
│  │  Showing 1–25 of 842         [←] 1 2 3 … 34 [→]              │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

### 5.2 Key Interactions

- **Tab switch:** replaces table data; URL updates with `?tab=sources` etc.; no full page reload
- **Row click:** opens right-side detail panel (320px) with a mini time-series for that page/source + full metric breakdown
- **Search:** filters rows client-side for visible data; full server search on submit/Enter
- **Metric selector:** changes the primary sort column and the inline bar chart in the table
- **UTM tab:** renders three sub-tabs (Campaign / Medium / Source) each with their own table

---

## Screen 6 — Site Settings

**Route:** `/settings`  
**Purpose:** Configure the tracked site, manage tracking snippets, team members, and account.

### 6.1 Layout

```
┌──────────────────────────────────────────────────────────────────────┐
│  Settings                                                            │
├──────────┬───────────────────────────────────────────────────────────┤
│          │                                                           │
│ General  │  General                                                  │
│ Tracking │  ────────────────────────────────────────────────────    │
│ Goals    │  Site name:   [My Website                           ]    │
│ Team     │  Domain:      [mysite.com                           ]    │
│ Shared   │  Timezone:    [Europe/Paris (UTC+1)              ▾  ]    │
│ Danger   │                                                           │
│          │  ────────────────────────────────────────────────────    │
│          │  Tracking snippet                                         │
│          │  ┌──────────────────────────────────────────────────┐    │
│          │  │ <script defer data-domain="mysite.com"           │    │
│          │  │   src="https://cdn.statflow.io/sf.js">           │    │
│          │  │ </script>                          [Copy] [Docs] │    │
│          │  └──────────────────────────────────────────────────┘    │
│          │                                                           │
│          │  ┌─ Status ──────────────────────────────────────────┐   │
│          │  │  ✓ Tracking active · Last event 2 minutes ago     │   │
│          │  └───────────────────────────────────────────────────┘   │
│          │                                                           │
│          │                              [Save changes]              │
│          │                                                           │
└──────────┴───────────────────────────────────────────────────────────┘
```

### 6.2 Settings Sub-Sections

**Tracking:**

- Custom events documentation
- Excluded IP addresses (comma-separated)
- Bot filtering toggle
- Data retention period selector
- Cookie-less confirmation note (locked, informational)

**Goals:**

- Table of custom goals/events
- New goal button → modal with event name + optional property conditions

**Team:**

- Members table: avatar, name, email, role badge, joined date, revoke button
- Invite member form (email + role select)
- Role definitions (four roles — ADR-0009): Owner / Admin / Editor / Viewer
  - Owner: full control incl. billing and team deletion
  - Admin: site + member + API-key management, all data
  - Editor: create/edit sites, goals, funnels, segments, reports
  - Viewer: read-only dashboards, analytics, saved reports

**Shared Dashboards:**

- Toggle to generate a public share link
- Link display with copy button
- Password-protect option
- Expiry date optional

**Danger Zone:**

- "Reset all data" (requires typing site name to confirm)
- "Delete site" (requires typing site name to confirm)
Both actions use a dedicated confirmation modal with `alert-dialog` (Escape does not close).

---

## Screen 7 — Auth / Login

**Route:** `/login`, `/register`, `/forgot-password`  
**Purpose:** Authentication entry point. Minimal, focused, no distraction.

### 7.1 Login Layout

```
┌─────────────────────────────────────────────────────────────────────┐
│                          [Theme toggle]                             │
│                                                                     │
│              ┌───────────────────────────────────────┐             │
│              │                                       │             │
│              │   ◆ Statflow                          │             │
│              │   Sign in to your account             │             │
│              │                                       │             │
│              │   Email                               │             │
│              │   [you@example.com                ]   │             │
│              │                                       │             │
│              │   Password                            │             │
│              │   [••••••••                     👁]   │             │
│              │                                       │             │
│              │   [Sign in  →]            Forgot?     │             │
│              │                                       │             │
│              │   No account? Create one              │             │
│              │                                       │             │
│              └───────────────────────────────────────┘             │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

Background: `--sf-bg-base` (very dark). Card: `--sf-bg-surface` with `--sf-shadow-xl`. Logo mark uses the indigo gradient.

**v1 ships email + password authentication only.** There are no OAuth
("continue with GitHub/Google") buttons on the v1 login screen — OAuth and SSO
are deferred post-v1 (ADR-0009). The screen offers Sign in, a "Forgot?" link to
`/forgot-password`, and a "Create one" link to `/register`. Password fields
enforce a 12-character minimum on every form (login, register, reset).

### 7.2 States

- **Loading:** Sign in button shows spinner; fields disabled
- **Error:** Toast (variant=error) for invalid credentials; field border turns `--sf-negative`
- **Success:** Redirect to `/` with a welcome toast

### 7.3 Register

Same card layout, adds: full name field, password confirmation, terms checkbox. Multi-step optionally: step 1 = account, step 2 = first site setup (domain + name).

### 7.4 Forgot Password

Single email field + submit. Success shows a message in the card: "Check your inbox — we sent a reset link to <you@example.com>".

### 7.5 Responsive

Full-screen centered card on all breakpoints. Card width: 400px on desktop; `calc(100vw − 32px)` on mobile with no box shadow.

---

## Screen 8 — Public Shared Dashboard

**Route:** `/share/:token`  
**Purpose:** A read-only, publicly accessible view of the dashboard. No auth required. Can be optionally password-protected.

### 8.1 Layout

```
┌─────────────────────────────────────────────────────────────────────┐
│  statflow                         [Powered by Statflow — free ↗]   │
│  Analytics for mysite.com                                           │
│  Dec 1 – Dec 31, 2024                                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐              │
│  │ Sessions │ │  Users   │ │Pageviews │ │Bounce %  │              │
│  │ 84,203   │ │ 61,412   │ │ 214,880  │ │  42.1%   │              │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘              │
│                                                                     │
│  [Sessions over time chart]                                         │
│                                                                     │
│  [Top Pages]                [Traffic Sources]                       │
│                                                                     │
│  [Geography map]            [Devices]                               │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### 8.2 Differences from Authenticated Dashboard

- No sidebar, no topbar navigation
- Minimal header: logo + site name + date range (display only, no picker)
- No filter controls, no export, no settings
- "Powered by Statflow" badge in header (links to statflow.io, opens in new tab)
- Period shown is whatever the site owner has configured in the share settings
- Theme follows the visitor's OS `prefers-color-scheme` rather than the owner's preference

### 8.3 Password Protection

If the share link is password-protected, the visitor sees a centered card (same style as login) with a single password field. On success, the token is stored in `sessionStorage` and the dashboard renders.

### 8.4 Responsive

Full responsive — same breakpoints as the main dashboard. Metric cards stack to 2-column on tablet, 1-column on mobile.

---

## Cross-Screen Interaction Patterns

### Navigation transitions

- Route changes use a 200ms fade-only transition (no slide) to avoid motion sickness
- Stale data remains visible and desaturated until new data arrives (no flash of emptiness)

### Global date range propagation

- Changing the date range in the topbar propagates to all `@tanstack/vue-query` queries via a shared Pinia store key
- All charts simultaneously enter skeleton state, then resolve independently as their queries complete

### Error recovery

- Network errors show an inline "Failed to load — Retry" state within each chart card
- Global API auth errors (401) trigger a redirect to `/login` with a toast explaining the session expired

### Keyboard shortcuts

| Shortcut | Action |
|---|---|
| `⌘K` / `Ctrl+K` | Open command palette |
| `⌘R` / `Ctrl+R` | Jump to Realtime |
| `G` then `O` | Go to Overview |
| `G` then `F` | Go to Funnels |
| `G` then `S` | Go to Settings |
| `[` / `]` | Navigate to previous/next period |
| `Escape` | Close any open modal/drawer/popover |
| `?` | Show keyboard shortcuts reference |
