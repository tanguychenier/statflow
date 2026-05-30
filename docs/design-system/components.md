# Statflow — Component Inventory

> Version 1.0  
> Stack: shadcn-vue · Reka UI · Tailwind CSS 4 · Vue 3 + TypeScript

---

## 1. Sourcing Legend

| Symbol | Meaning |
|---|---|
| `[shadcn]` | Direct from shadcn-vue — thin wrapper only, tokens applied |
| `[reka]` | Built on a Reka UI headless primitive — full styling ownership |
| `[custom]` | Hand-built component, no headless primitive |

Every component that accepts a `class` prop passes it through to the root element. Every component exposes the full set of semantic design tokens via CSS variables rather than hard-coded values.

---

## 2. Buttons

**Source:** `[shadcn]` (uses `shadcn-vue Button`)

### 2.1 Variants

| Variant | Description | Usage |
|---|---|---|
| `default` | Filled with `--sf-accent`, white text | Primary CTAs |
| `secondary` | `--sf-bg-subtle` fill, `--sf-fg-primary` text | Secondary actions |
| `ghost` | Transparent, `--sf-fg-secondary` text, subtle hover bg | Toolbar actions, icon buttons |
| `outline` | `--sf-border` border, no fill | Tertiary actions |
| `destructive` | `--sf-negative` fill | Delete / irreversible actions |
| `link` | No bg, underline on hover | Inline text actions |

### 2.2 Sizes

| Size | Height | Padding H | Font size | Radius |
|---|---|---|---|---|
| `xs` | 28px | 10px | `--sf-text-xs` | `--sf-radius-md` |
| `sm` | 32px | 12px | `--sf-text-sm` | `--sf-radius-md` |
| `default` | 36px | 16px | `--sf-text-sm` | `--sf-radius-md` |
| `lg` | 40px | 20px | `--sf-text-base` | `--sf-radius-md` |
| `icon` | 36×36px | — | — | `--sf-radius-md` |
| `icon-sm` | 28×28px | — | — | `--sf-radius-md` |

### 2.3 States

- **Hover:** `--sf-accent-hover` bg (default variant); `--sf-bg-subtle` (ghost/secondary)
- **Active:** scale(0.97), 100ms `--sf-ease-default`
- **Focus-visible:** 2px ring `--sf-border-focus`, 2px offset
- **Disabled:** 40% opacity, cursor `not-allowed`
- **Loading:** Left-slot spinner replaces icon; text unchanged; pointer-events blocked

### 2.4 Icon Button

Variant `ghost` + size `icon` / `icon-sm` + a Lucide icon child. No visible label. Always has `aria-label`.

---

## 3. Inputs & Form Controls

### 3.1 Text Input `[shadcn]`

```
height: 36px
border: 1px solid var(--sf-border)
border-radius: var(--sf-radius-md)
background: var(--sf-bg-overlay)
padding: 0 12px
font-size: var(--sf-text-sm)
```

States: default → hover (border `--sf-border-strong`) → focus (ring 1.5px `--sf-border-focus`) → error (border `--sf-negative`) → disabled.

Slots: leading icon, trailing icon/action.

### 3.2 Textarea `[shadcn]`

Same visual as text input. `min-height: 80px`, `resize: vertical`. Auto-grow variant available via JS.

### 3.3 Select `[reka]` (Reka UI Select)

Trigger matches text input sizing. Dropdown uses `--sf-bg-overlay` background, `--sf-shadow-lg`. Options have 32px row height, 8px H padding. Selected option shows checkmark. Search input inside dropdown for lists > 8 items.

### 3.4 Combobox `[reka]` (Reka UI Combobox)

Inline search field within dropdown. Supports multi-select (checkboxes per item) and single select. Keyboard: arrow navigation, Enter to select, Escape to close.

### 3.5 Checkbox `[shadcn]`

16×16px. Custom checkmark SVG. Three states: unchecked, checked (`--sf-accent` fill), indeterminate (dash). Always paired with a `<label>` element.

### 3.6 Radio Group `[shadcn]`

Custom radio circles, 16px. Vertical or horizontal layout.

### 3.7 Switch / Toggle `[shadcn]`

Pill shape, 36×20px. Thumb slides with CSS transition 150ms `--sf-ease-default`. Track: `--sf-bg-muted` off / `--sf-accent` on.

### 3.8 Slider `[reka]`

Single and range variants. Track 4px height, thumb 16px circle. Used for date-range selection and heatmap intensity threshold.

### 3.9 Date-Range Picker `[custom + reka]`

A compound component built from:

- Reka UI Popover for the floating panel
- Two calendar month views side-by-side on desktop, stacked on mobile
- Preset chips: Today / Yesterday / Last 7d / Last 30d / Last 90d / This Month / Last Month / Custom
- Text inputs for manual date entry (formatted via `Intl.DateTimeFormat`)
- Compare toggle: enables a second range (dashed overlay on charts)

```
Trigger: Button variant=outline, shows formatted range "Dec 1 – Dec 31, 2024"
Panel width: 640px (desktop), full-width bottom sheet (mobile)
```

### 3.10 Filter / Segment Builder `[custom + reka]`

A multi-condition query builder for audience segments and funnel filters.

Structure:

```
[AND | OR]  ← group operator pill
  ┌────────────────────────────────────────┐
  │ [dimension ▾]  [operator ▾]  [value]  │ × remove
  └────────────────────────────────────────┘
  + Add condition
```

- Dimension select: searchable combobox populated from the API's dimension manifest
- Operator select: contextual to dimension type (string → contains/is/is not/starts with; number → >/</=/between; date → before/after/between)
- Value: text input, multi-value combobox, or date picker depending on operator
- Nested groups: each group can have its own AND/OR operator
- Max depth: 2 levels (group > conditions)

---

## 4. Cards

### 4.1 Base Card `[shadcn]`

```
background: var(--sf-bg-surface)
border: 1px solid var(--sf-border)
border-radius: var(--sf-radius-xl)
padding: 20px 24px
box-shadow: var(--sf-shadow-sm)
```

Slots: `header` (title + optional trailing action), `default` (body), `footer`.

### 4.2 Metric Card `[custom]`

Compact KPI card for the overview grid.

```
┌─────────────────────────────┐
│ Page views              ··· │  ← title + overflow menu
│                             │
│ 1,284,902                   │  ← display-lg / display-md
│ ▲ 12.4%  vs prev period     │  ← trend badge + comparison label
│                             │
│ ▓▓▓▓▓▓▓▓▓░░░░              │  ← spark line (mini ECharts, 64px tall)
└─────────────────────────────┘
```

Props: `title`, `value`, `trend` (number, positive/negative/neutral), `sparkData` (array), `loading` (shows skeleton).

### 4.3 Chart Card `[custom]`

Expanded card wrapping a chart. Header row: title (left) + optional legend + actions (right: download CSV, expand, more). Body contains the chart wrapper at full height. Footer optional for chart footnote.

### 4.4 Stat Row Card `[custom]`

Horizontal layout of 3–5 metric pills. Used in sidebar panels and detail drawers.

---

## 5. Data Tables

**Source:** `[custom]` — built on `@tanstack/vue-query` for data fetching and a headless table utility.

### 5.1 Feature Set

| Feature | Notes |
|---|---|
| Sorting | Click column header; multi-sort with Shift+click |
| Column visibility | Column picker in toolbar via Combobox |
| Pagination | Page-based; server-side |
| Row selection | Checkbox per row; bulk action bar appears when any selected |
| Inline expansion | Row expands to a detail section (sub-table or metrics) |
| Sticky columns | First column always sticky |
| Column resize | Drag handle on header separator |
| Pinning | Pin columns left/right via column context menu |
| Empty state | Integrated empty state slot |
| Loading state | Skeleton rows (10 by default) |
| Search/filter | Toolbar search input + active filter chips |
| Row actions | Trailing icon-button group, revealed on hover |
| Density | Compact (32px rows) / Default (40px) / Relaxed (48px) |

### 5.2 Visual Anatomy

```
┌─ Toolbar ──────────────────────────────────────────────────────┐
│ [Search...]  [Filter ▾]  [Date range]     [Columns ▾] [Export] │
│ Active filters: Source = Google ×  Device = Mobile ×  Clear    │
└────────────────────────────────────────────────────────────────┘
┌─ Table ────────────────────────────────────────────────────────┐
│ ☐  Page ↑↓   Sessions ↑↓  Bounce %  Avg duration  Conversion  │
├────────────────────────────────────────────────────────────────┤
│ ☐  /pricing  12,043        42.1%     1m 24s         3.2%       │
│ ☐  /docs     9,812         28.4%     3m 01s         0.8%       │
│    ...                                                          │
├────────────────────────────────────────────────────────────────┤
│ Showing 1–25 of 1,284    [←] [1][2][3]...[52] [→]             │
└────────────────────────────────────────────────────────────────┘
```

### 5.3 Number & Percentage Cells

Numbers right-aligned, monospace font (`--sf-font-mono`). Percentage cells optionally include an inline bar (15px height, `--sf-accent-subtle` fill, `--sf-accent` active fill).

---

## 6. Chart Wrappers

See `data-viz.md` for the full charting strategy. This section covers the wrapper component API.

### 6.1 `<ChartWrapper>` `[custom]`

A HOC (Higher-Order Component) that wraps every ECharts instance. Responsibilities:

- Injects the Statflow ECharts theme (token-driven, updated when `data-theme` changes)
- Handles resize via `ResizeObserver`
- Emits `click`, `legendChange`, `dataZoom` events
- Shows `<ChartSkeleton>` during loading
- Shows `<ChartEmpty>` when `data` is empty
- Applies `aria-label` from `title` prop; chart rendered in `role="img"` container

### 6.2 Chart Sub-Components

| Component | Chart type | Notes |
|---|---|---|
| `<TimeSeriesChart>` | ECharts Line / Area | Multi-series, zoom brush, comparison overlay |
| `<BarChart>` | ECharts Bar | Horizontal + vertical; stacked variant |
| `<FunnelChart>` | ECharts Funnel (custom) | Step-by-step conversion, drop-off %, tooltip |
| `<RetentionGrid>` | ECharts Heatmap | Cohort grid; colour scale from token palette |
| `<GeoMap>` | ECharts Map | World or country; choropleth sessions/users |
| `<HeatmapOverlay>` | Canvas (custom) | Pointer heatmap overlaid on live page screenshot |
| `<DonutChart>` | ECharts Pie (ring) | Device/browser split; legend right |
| `<SparkLine>` | ECharts Line (micro) | 64px tall, no axes, used inside MetricCard |
| `<ProgressBar>` | CSS only | Horizontal bar for top-N lists |

---

## 7. Date-Range Picker (standalone)

Documented in section 3.9 above. Exported as `<DateRangePicker>`. Used in the global topbar and within individual chart cards for local overrides.

---

## 8. Command Palette (⌘K)

**Source:** `[reka]` (Reka UI Dialog + custom list rendering)

Triggered by `Ctrl+K` / `⌘K` or the search icon in the topbar.

### 8.1 Features

- Full-screen dim overlay (`rgba(0,0,0,0.5)` backdrop)
- Modal panel: 560px wide, centered, `--sf-radius-xl`, `--sf-shadow-xl`
- Search input at top with leading search icon and trailing `Esc` hint
- Sections: Recent, Pages, Settings, Actions — with divider labels
- Items: icon + label + optional shortcut badge
- Fuzzy search across all routes, settings, and help articles
- Keyboard: arrow keys navigate, Enter activates, Escape closes
- Empty state: "No results for '…'" with suggestion to check spelling

### 8.2 Anatomy

```
┌─────────────────────────────────────────────────────┐
│ 🔍  Search pages, settings, actions...          Esc │
├─────────────────────────────────────────────────────┤
│ RECENT                                              │
│  📊  Overview                                       │
│  🔥  Heatmaps — Homepage                           │
├─────────────────────────────────────────────────────┤
│ NAVIGATE                                            │
│  📈  Funnels                              ⌘ F      │
│  ⏱   Realtime                             ⌘ R      │
│  🗂   Pages & Sources                               │
│  ⚙️   Site Settings                                │
├─────────────────────────────────────────────────────┤
│ ACTIONS                                             │
│  ➕  New Funnel                                     │
│  📤  Export current view as CSV                    │
└─────────────────────────────────────────────────────┘
```

---

## 9. Navigation & Sidebar

**Source:** `[custom]` — no headless primitive; uses vue-router.

### 9.1 Layout

```
┌──────┬────────────────────────────────────┐
│      │  Topbar                            │
│ Side │────────────────────────────────────│
│  bar │                                    │
│      │  Content area                      │
│      │                                    │
└──────┴────────────────────────────────────┘
```

### 9.2 Sidebar Component

- **Collapsed state** (56px wide): icon-only, tooltip on hover
- **Expanded state** (220px wide): icon + label
- **Mobile** (< 768px): hidden by default, slides in as drawer from left (Reka UI Dialog)
- Collapse toggle button pinned to bottom-left
- State persisted in `localStorage` via `useSidebarState()` composable

**Navigation sections:**

```
Logo / brand mark
─────────────────
Overview          (LayoutDashboard icon)
Realtime          (Radio icon)
─────────────────
Behaviour
  └ Heatmaps      (Flame icon)
  └ Click Maps    (MousePointer icon)
  └ Session Rec.  (Video icon)  [pro badge]
Pages & Sources   (FileBarChart icon)
Funnels           (Filter icon)
Retention         (RotateCcw icon)
Journeys          (GitMerge icon)
─────────────────
Settings          (Settings2 icon)
─────────────────
[Avatar] User     ↕ (AccountMenu)
```

Active state: left border 2px `--sf-accent`, background `--sf-accent-subtle`, text `--sf-fg-primary`.

### 9.3 Topbar

Height: 56px. Contents:

```
[≡ collapse toggle]  [Site selector ▾]    [Date range picker]  [⌘K]  [🔔]  [Avatar]
```

- **Site selector:** Combobox listing all tracked sites; current site prominently displayed
- **Date range:** Global date range; overridable per-chart
- **Command palette trigger:** Icon button
- **Notification bell:** Badge with unread count; dropdown with notification list
- **Avatar:** Dropdown with profile, settings, theme toggle, sign out

---

## 10. Modals & Dialogs

**Source:** `[shadcn]` (wraps Reka UI Dialog)

Standard modal structure: backdrop, centered panel.

| Size | Width | Usage |
|---|---|---|
| `sm` | 400px | Confirmation dialogs |
| `md` | 560px | Forms, settings |
| `lg` | 720px | Complex forms, data views |
| `xl` | 960px | Expanded chart view |
| `full` | 100vw − 48px | Session recordings, heatmap detail |

Header: title (left) + close button (right). Body: scrollable. Footer: action buttons (right-aligned, primary + cancel).

### 10.1 Confirmation Dialog `[custom]`

Predefined `sm` modal for destructive confirmations. Props: `title`, `description`, `confirmLabel`, `confirmVariant` (default `destructive`). Confirm button focused by default (keyboard-accessible).

### 10.2 Alert Dialog `[shadcn]`

Uses Reka UI AlertDialog; focus is trapped inside. Escape does not close it (user must explicitly choose an action) for destructive operations.

---

## 11. Toasts & Notifications

**Source:** `[shadcn]` (Sonner-style, wraps Reka UI Toast)

### 11.1 Toast Variants

| Variant | Icon | Border accent | Usage |
|---|---|---|---|
| `default` | — | `--sf-border` | Info messages |
| `success` | CheckCircle | `--sf-positive` | Successful operations |
| `error` | XCircle | `--sf-negative` | Failures |
| `warning` | AlertTriangle | `--sf-warning` | Warnings |
| `loading` | Spinner | `--sf-border` | Async operations |

### 11.2 Behaviour

- Position: bottom-right on desktop, bottom-center on mobile
- Stack: up to 3 visible, older ones collapse behind newest (Sonner-style stack)
- Auto-dismiss: 4s (success/default), 6s (warning), no auto-dismiss (error)
- Action button: optional CTA within the toast (e.g. "Undo")
- Pause on hover

---

## 12. Skeleton Loaders

**Source:** `[custom]`

`<Skeleton>` renders a `div` with a shimmer animation (`background-size: 200%; animation: shimmer 1.5s infinite`). Two helpers:

- `<SkeletonText lines="3">` — lines of text-shaped blocks
- `<SkeletonCard>` — full card placeholder matching MetricCard proportions

Used universally during `isLoading` states from `@tanstack/vue-query`. See motion tokens for reduced-motion handling.

---

## 13. Empty States

**Source:** `[custom]`

Used when a data source returns zero rows / no configuration exists.

```
┌─────────────────────────────────────────────────────┐
│                                                     │
│               [Illustration SVG]                   │
│                                                     │
│           No data for this period                  │
│     Try adjusting your date range or filters.      │
│                                                     │
│              [Adjust filters]                       │
│                                                     │
└─────────────────────────────────────────────────────┘
```

Props: `illustration` (SVG name from a preset library), `title`, `description`, `action` (button config).

Illustrations are minimal line-art SVGs using `currentColor` — they automatically adapt to both themes.

Variants:

- `no-data` — chart with no points
- `no-results` — search/filter returned nothing
- `no-setup` — feature not yet configured (e.g. no funnels created)
- `no-access` — permissions error
- `error` — API error with retry button

---

## 14. Badges & Chips

**Source:** `[custom]`

### 14.1 Badge

Inline pill: `border-radius: full`, `padding: 2px 8px`, `font-size: var(--sf-text-2xs)`, uppercase, `letter-spacing: var(--sf-tracking-widest)`.

Variants: `default`, `success`, `warning`, `error`, `info`, `outline`, `accent`.

### 14.2 Chip (filter chip)

Interactive removable chip. Shows a label and an `×` remove button. Used in the active filters row above data tables and in the segment builder.

### 14.3 Pro Badge

Gradient pill (`--sf-indigo-500` → `--sf-violet-500`), "Pro" label. Used on locked features.

---

## 15. Tooltips & Popovers

### 15.1 Tooltip `[shadcn]`

`Reka UI Tooltip` under the hood. 150ms open delay, 0ms close delay. 8px arrow. Max-width 240px. `z-index: 50`.

### 15.2 Popover `[shadcn]`

Larger floating panel, no arrow. Used for column config, advanced filter options, user mentions.

---

## 16. Tabs & Segmented Controls

### 16.1 Tabs `[shadcn]` (Reka UI Tabs)

Underline variant (line indicator slides between active tab with transition). Used for sub-navigation within pages (e.g. Heatmaps > Clicks | Scroll | Movement).

### 16.2 Segmented Control `[custom]`

Pill-within-pill. Background pill contains the options; the selected option has a white/surface background with shadow. Used for small option sets (2–4 items): e.g. "Table / Chart", "Daily / Weekly / Monthly".

---

## 17. Avatar & User Indicators

**Source:** `[shadcn]`

Sizes: 24px / 32px / 40px / 56px. Falls back from image → initials → generic icon. Presence dot optional (for realtime collaborators).

---

## 18. Divider

**Source:** `[custom]`

`<hr>` with `border-top: 1px solid var(--sf-border)`. Variants: horizontal, vertical (for toolbar separators). Optional `label` slot for section dividers ("or continue with").

---

## 19. Scroll Area

**Source:** `[shadcn]`

Custom scrollbar: 6px wide, thumb `--sf-neutral-700` (dark) / `--sf-neutral-300` (light), rounded, auto-hide after 1s inactivity.

---

## 20. Code Block

**Source:** `[custom]`

Used in the tracking snippet setup flow. Syntax highlighted via `shiki` (lazy-loaded). Copy button in top-right. Dark surface background regardless of active theme.

---

## Component Count Summary

| Category | Components | Source |
|---|---|---|
| Buttons | 1 base + 6 variants + 2 icon sizes | shadcn |
| Inputs | 10 (text, textarea, select, combobox, checkbox, radio, switch, slider, date-range, filter-builder) | shadcn + reka + custom |
| Cards | 4 (base, metric, chart, stat-row) | shadcn + custom |
| Data Table | 1 (feature-rich) | custom |
| Charts | 9 wrappers | custom |
| Command Palette | 1 | reka + custom |
| Navigation | 2 (sidebar, topbar) | custom |
| Modals | 2 (modal, confirm dialog) | shadcn |
| Toasts | 1 (5 variants) | shadcn |
| Skeletons | 3 (base, text, card) | custom |
| Empty States | 1 (5 variants) | custom |
| Badges & Chips | 3 (badge, chip, pro-badge) | custom |
| Tooltips & Popovers | 2 | shadcn |
| Tabs & Segmented | 2 | shadcn + custom |
| Misc | 5 (avatar, divider, scroll area, code block, progress bar) | shadcn + custom |
| **Total** | **~50 components** | |
