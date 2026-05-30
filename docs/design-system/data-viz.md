# Statflow — Data Visualization Strategy

> Version 1.0  
> Stack: Vue 3 + TypeScript + Vite

---

## 1. Library Recommendation: ECharts

**Verdict: Apache ECharts 5.x via `echarts-for-vue` (or direct `init` + Vue composable wrapper).**

### 1.1 ECharts vs. unovis

| Criterion | ECharts 5 | unovis |
|---|---|---|
| **Chart types** | 20+ native types including geo maps, heatmaps, funnel, scatter, radar, calendar | ~15 types; geo and heatmap require extra work |
| **Performance** | Canvas-based by default; WebGL renderer (`echarts-gl`) for 1M+ data points | SVG-first; can strain at high data volumes |
| **Bundle size** | Tree-shakable via `echarts/core` import; essential charts ~120kB gzipped | ~80kB gzipped; leaner but fewer types |
| **Vue 3 integration** | Official `vue-echarts` wrapper or thin composable | Native TS/Vue components |
| **Theming** | First-class `registerTheme()` API; runtime theme switching trivial | CSS variables work but theme API is limited |
| **Geo/Map** | Native GeoJSON support, built-in projection handling | No native choropleth |
| **Heatmap** | Native `heatmap` series; no extra canvas layer | No native screen heatmap |
| **Accessibility** | `aria` option for title/description; keyboard navigation limited | Similar limitations |
| **Community** | Apache project; 60k+ GitHub stars; long-term maintenance guaranteed | Newer project; smaller community |
| **License** | Apache-2.0 — compatible with AGPL-3.0 project | MIT — compatible |
| **Docs quality** | Excellent; large example library | Good but thinner |

**Decision: ECharts** wins on the breadth of chart types needed (funnel, retention heatmap, geo choropleth, click heatmap) and its theming API, which integrates cleanly with the CSS variable token system. The bundle-size delta is acceptable given tree-shaking.

unovis would be a fine choice for a simpler analytics product, but Statflow's behavioral analytics module (heatmaps, retention grids, geo maps) requires the full breadth only ECharts provides without stitching together multiple libraries.

### 1.2 Integration Approach

```typescript
// src/composables/useChart.ts
// Thin wrapper — no dependency on vue-echarts package
// Gives full control over instance lifecycle

import { onMounted, onBeforeUnmount, ref, watch, type Ref } from 'vue'
import * as echarts from 'echarts/core'
import { registerTheme } from 'echarts/core'
import { statflowDark, statflowLight } from '@/chart-themes'
import { useThemeStore } from '@/stores/theme'

export function useChart(el: Ref<HTMLElement | null>, option: Ref<echarts.EChartsOption>) {
  const chart = ref<echarts.ECharts | null>(null)
  const themeStore = useThemeStore()

  onMounted(() => {
    if (!el.value) return
    registerTheme('statflow-dark', statflowDark)
    registerTheme('statflow-light', statflowLight)
    const theme = themeStore.isDark ? 'statflow-dark' : 'statflow-light'
    chart.value = echarts.init(el.value, theme, { renderer: 'canvas' })
    chart.value.setOption(option.value)
  })

  watch(option, (newOption) => {
    chart.value?.setOption(newOption, { notMerge: false, lazyUpdate: true })
  }, { deep: true })

  watch(() => themeStore.isDark, (isDark) => {
    chart.value?.dispose()
    const theme = isDark ? 'statflow-dark' : 'statflow-light'
    chart.value = echarts.init(el.value!, theme, { renderer: 'canvas' })
    chart.value.setOption(option.value)
  })

  onBeforeUnmount(() => chart.value?.dispose())

  return { chart }
}
```

Tree-shaking imports per chart type:

```typescript
// Only register the renderers and chart types actually used
import { LineChart, BarChart, FunnelChart, HeatmapChart,
         MapChart, PieChart, ScatterChart } from 'echarts/charts'
import { CanvasRenderer } from 'echarts/renderers'
import { GridComponent, TooltipComponent, LegendComponent,
         DataZoomComponent, VisualMapComponent,
         GeoComponent, ToolboxComponent } from 'echarts/components'

echarts.use([
  LineChart, BarChart, FunnelChart, HeatmapChart, MapChart,
  PieChart, ScatterChart, CanvasRenderer, GridComponent,
  TooltipComponent, LegendComponent, DataZoomComponent,
  VisualMapComponent, GeoComponent, ToolboxComponent
])
```

Estimated final bundle after tree-shaking: ~140kB gzipped.

---

## 2. ECharts Theme Definition

The Statflow ECharts theme object is generated from design tokens at build time (a small Vite plugin reads `tokens.css` and outputs `src/chart-themes/index.ts`).

Key theme properties:

```typescript
// src/chart-themes/dark.ts
export const statflowDark = {
  color: [
    '#6366f1', // chart-1 indigo
    '#8b5cf6', // chart-2 violet
    '#0ea5e9', // chart-3 sky
    '#10b981', // chart-4 emerald
    '#f59e0b', // chart-5 amber
    '#f43f5e', // chart-6 rose
    '#a78bfa', // chart-7 lavender
    '#34d399', // chart-8 mint
  ],
  backgroundColor: 'transparent',
  textStyle: {
    fontFamily: 'Inter, ui-sans-serif, system-ui',
    color: '#71717a',      // --sf-fg-secondary
    fontSize: 12,
  },
  title: { textStyle: { color: '#fafafa', fontSize: 14, fontWeight: 600 } },
  legend: { textStyle: { color: '#a0a0ab' } },
  tooltip: {
    backgroundColor: '#18181b',  // --sf-bg-overlay
    borderColor: '#3f3f46',      // --sf-border-strong
    borderWidth: 1,
    textStyle: { color: '#fafafa' },
    extraCssText: 'box-shadow: 0 8px 24px rgba(0,0,0,0.60); border-radius: 8px;',
  },
  axisLine: { lineStyle: { color: '#27272a' } },
  axisTick: { lineStyle: { color: '#27272a' } },
  axisLabel: { color: '#71717a' },
  splitLine: { lineStyle: { color: '#27272a', type: 'dashed' } },
}
```

---

## 3. Chart Type Mapping

### 3.1 Time Series — Sessions / Users / Pageviews over Time

**Type:** ECharts `line` with `areaStyle`  
**Component:** `<TimeSeriesChart>`

| Property | Value |
|---|---|
| Renderer | Canvas |
| Series style | Area fill with gradient (solid → transparent), 2px stroke |
| Smoothing | `smooth: true` (0.5 tension) |
| Comparison | Second series with dashed stroke + 50% opacity fill |
| Interaction | Crosshair tooltip (shared X axis), data zoom brush |
| X axis | Category (dates), auto-formatted by interval |
| Y axis | Value, auto-scaled, short number format (1.2k, 3.4M) |
| Legend | Top-right, hidden when single series |
| Zoom | `dataZoom` component — slider at bottom + inside scroll |
| Empty | `<ChartEmpty>` when all values are 0 or array is empty |

Granularity options driven by date range: ≤ 2 days → hourly; ≤ 90 days → daily; > 90 days → weekly.

### 3.2 Bar Chart — Top Pages, Sources, Countries

**Type:** ECharts `bar` (horizontal)  
**Component:** `<BarChart>`

| Property | Value |
|---|---|
| Orientation | Horizontal (Y = category labels, X = value) |
| Bar height | 20px, `barCategoryGap: 40%` |
| Color | Single series: `--sf-chart-1`; multi-series: full palette |
| Value label | Shown inside/outside bar end, short format |
| Axis | Y-axis labels truncated at 20 chars with tooltip on hover |
| Comparison | Second series `--sf-chart-2` with lighter alpha fill |
| Rank numbers | Custom renderer: small grey ordinal before each bar |

Vertical variant available for time-series bar (e.g. weekly comparison).

### 3.3 Funnel Chart — Conversion Funnels

**Type:** Custom render — NOT ECharts native funnel (too decorative)  
**Component:** `<FunnelChart>`

The native ECharts funnel creates a trapezoid shape that doesn't match Statflow's data-dense aesthetic. Instead, `<FunnelChart>` renders a stepped horizontal bar layout using ECharts `bar` with custom `itemStyle.borderRadius` + label positioning:

```
Step 1   ████████████████████████████████████  8,402  100%
          ↓ 31.8% dropped
Step 2   ████████████████████████              5,730   68.2%
          ↓ 47.9% dropped
Step 3   ████████████                          2,986   35.5%
          ↓ 65.3% dropped
Step 4   ██████                                1,036   12.3%
```

Drop-off arrows are SVG overlays positioned between bar rows. Tooltip on hover shows absolute numbers + conversion from step 1 + conversion from previous step.

### 3.4 Retention Grid — Cohort Retention

**Type:** ECharts `heatmap` on a `grid` with `visualMap`  
**Component:** `<RetentionGrid>`

| Property | Value |
|---|---|
| X axis | Week number (0, 1, 2, … 12) — time since cohort entry |
| Y axis | Cohort week (Jan W1, Jan W2, …) |
| Cell color | `visualMap` continuous: 0% = `--sf-neutral-800` → 100% = `--sf-indigo-500` |
| Cell label | Percentage inside each cell; `--sf-text-2xs`, hidden below 6% for space |
| Tooltip | Cohort date + week offset + retained users + % |
| Diagonal | Week 0 always 100% — visually distinct with `--sf-indigo-700` |
| Missing data | Cell rendered in `--sf-bg-muted` with "—" label |

### 3.5 Geographic Map — Sessions by Country

**Type:** ECharts `map` + GeoJSON  
**Component:** `<GeoMap>`

| Property | Value |
|---|---|
| GeoJSON | World GeoJSON, lazy-loaded on first use (~400kB) |
| Projection | Natural Earth (ECharts default) |
| Color scale | `visualMap` continuous: low = `--sf-indigo-900` → high = `--sf-indigo-400` |
| Interaction | Hover tooltip (country + sessions + % of total); click to drilldown |
| Drilldown | Click country → zooms to country-level GeoJSON if available |
| No-data countries | `--sf-neutral-800` fill |
| Zoom | Mouse scroll + drag to pan; reset button |

Country GeoJSONs (for drilldown) are loaded on demand from a static CDN path.

### 3.6 Heatmap Overlay — Click & Scroll Maps

**Type:** Custom Canvas — NOT ECharts  
**Component:** `<HeatmapOverlay>`

The click/scroll/movement heatmap is a separate concern from ECharts: it must overlay a screenshot of the tracked page at pixel-accurate coordinates.

Implementation:

- Backend stores click coordinates as `{ x_pct: float, y_pct: float, count: int }[]` (percentage-based, viewport-independent)
- Frontend renders a `<canvas>` element absolutely positioned over a `<img>` (screenshot)
- Heatmap rendering uses the `simpleheat` library (MIT, 3 kB) for the canvas gradient blending. This dependency is
  **frontend-only** — it lives in `apps/frontend` and never reaches `packages/tracker`, whose &lt; 2 KB core bundle
  mandates zero runtime dependencies.
- Intensity and radius are controlled by sliders (see `components.md`); changes trigger `canvas.redraw()` without new API calls
- Scroll heatmap: a horizontal gradient bar on the left side of the page showing how far users scrolled

### 3.7 Donut Chart — Device / Browser Split

**Type:** ECharts `pie` with `radius: ['55%', '75%']`  
**Component:** `<DonutChart>`

| Property | Value |
|---|---|
| Inner label | Total value centered (custom rich text renderer) |
| Legend | Right-aligned list with color dot + label + percentage |
| Hover | Slice expands 4px outward with tooltip |
| Max segments | 6; remainder grouped as "Other" |
| Minimum slice | < 1% slices collapsed into "Other" |

### 3.8 Spark Line — Metric Card Mini Chart

**Type:** ECharts `line` (micro, no axes)  
**Component:** `<SparkLine>`

| Property | Value |
|---|---|
| Size | Full card width × 64px height |
| Axes | Hidden (no grid, no axis labels) |
| Area fill | Gradient matching trend direction (green if positive, red if negative, neutral grey) |
| Interaction | Disabled — display only |
| Data points | 30 days of daily aggregates |
| Renderer | SVG preferred (lighter for many simultaneous instances on overview) |

### 3.9 Progress Bar — Top-N List Inline Bars

**Type:** CSS — NOT ECharts  
**Component:** `<ProgressBar>` (in-table)

Simple `div` with width driven by percentage value. Used in data tables alongside page/source names to give a visual weight without a separate chart. Height: 4px. Color: `--sf-accent-subtle` background, `--sf-accent` fill.

---

## 4. Tooltip Design

All ECharts tooltips follow a consistent template (injected via `tooltip.formatter`):

```
┌──────────────────────────────────┐
│  Dec 15, 2024                    │  ← header: date or category
├──────────────────────────────────┤
│  ● Sessions      12,043          │  ← series dot + name + value
│  ○ Prev period    9,812  ▲23.1%  │  ← comparison (dashed dot)
└──────────────────────────────────┘
```

- Numbers use `Intl.NumberFormat` (locale-aware, short notation for > 10k)
- Trend delta shows ▲/▼ + percentage + color `--sf-positive-text` / `--sf-negative-text`
- Tooltip follows cursor; `confine: true` to stay within chart bounds

---

## 5. Responsive Chart Behavior

| Breakpoint | Behavior |
|---|---|
| Desktop ≥ 1280px | Full chart, legend visible, zoom brush enabled |
| Tablet 768–1279px | Legend collapses to top; zoom brush hidden; font-size −1 |
| Mobile < 768px | Chart height reduced; X-axis labels rotate 45°; legend hidden (data in tooltip) |

All charts listen to a `ResizeObserver` via the `useChart` composable and call `chart.resize()` on element size change — no window resize listener needed.

---

## 6. Loading & Error States

| State | Behavior |
|---|---|
| `isLoading` | `<ChartSkeleton>` rendered in place of chart |
| `isError` | `<ChartEmpty variant="error">` with retry button |
| `isEmpty` | `<ChartEmpty variant="no-data">` with suggestion |
| Stale data | Chart remains visible but dimmed (50% opacity) during background refetch |

Stale-while-revalidate pattern: `@tanstack/vue-query` `staleTime: 60_000ms`, `gcTime: 300_000ms`. Charts never flash empty during navigation back to a previously loaded page.

---

## 7. Accessibility in Charts

Full details in `accessibility.md`. Summary:

- Every chart container has `role="img"` and `aria-label` from the chart title prop
- ECharts `aria.enabled: true` generates a hidden `<desc>` with a textual summary
- For data tables, all chart data is also available in the exportable table (keyboard/screen-reader users can access raw data)
- Color is never the sole means of encoding information: shape (line vs. dashed) and labels supplement color in multi-series charts
- Color palette passes contrast check for color-blind users (deuteranopia safe: indigo + amber + sky are distinguishable)
