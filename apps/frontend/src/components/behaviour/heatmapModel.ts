// =============================================================================
// Behaviour heatmap model — pure, framework-free logic (screens.md §2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// All decision logic for the Heatmaps screen lives here so it can be unit-tested
// without a DOM: mapping the four UX view types onto the two API heatmap types,
// translating a device toggle into a viewport-width filter, and shaping the raw
// breakdown / heatmap responses into the panel's view-models. The .vue files
// stay thin wiring layers around these functions.
// =============================================================================

import type {
  BreakdownRow,
  Filter,
  HeatmapClickPoint,
  HeatmapScrollDepth,
  HeatmapType,
} from '@/api/types'

/** The four overlays the screen offers (screens.md §2.1 "View type"). */
export type HeatmapViewType = 'clicks' | 'scroll' | 'movement' | 'rage'

export const HEATMAP_VIEW_TYPES: readonly HeatmapViewType[] = [
  'clicks',
  'scroll',
  'movement',
  'rage',
] as const

export type DeviceKind = 'desktop' | 'tablet' | 'mobile'

export const DEVICE_KINDS: readonly DeviceKind[] = ['desktop', 'tablet', 'mobile'] as const

/**
 * Viewport-width window (CSS px) sent to the heatmap endpoint per device. The
 * boundaries mirror the global breakpoints in screens.md (mobile < 768, tablet
 * 768–1023, desktop ≥ 1024) so the overlay only blends coordinates recorded at
 * a comparable rendered width.
 */
export function viewportRangeFor(device: DeviceKind): {
  viewport_width_min?: number
  viewport_width_max?: number
} {
  switch (device) {
    case 'mobile':
      return { viewport_width_min: 0, viewport_width_max: 767 }
    case 'tablet':
      return { viewport_width_min: 768, viewport_width_max: 1023 }
    case 'desktop':
      return { viewport_width_min: 1024 }
  }
}

/**
 * The API only models `click` and `scroll` heatmaps. `movement` and `rage`
 * reuse the click coordinate space — movement is the un-thresholded click map,
 * rage clicks are click coordinates restricted to rage-click events (the
 * restriction is expressed as an event_name filter on the companion breakdown,
 * not on the heatmap endpoint, which takes no filters).
 */
export function apiHeatmapTypeFor(view: HeatmapViewType): HeatmapType {
  return view === 'scroll' ? 'scroll' : 'click'
}

/** A view type carries a scroll overlay only when it is the scroll map. */
export function showsScrollOverlay(view: HeatmapViewType): boolean {
  return view === 'scroll'
}

/** Click/movement/rage overlays paint the radial click blobs. */
export function showsClickOverlay(view: HeatmapViewType): boolean {
  return view !== 'scroll'
}

/**
 * The companion `event_name` filter used to scope the insight breakdowns to the
 * active view. Clicks/movement look at plain clicks; rage/dead views look at the
 * dedicated behavioural events (event-contract §4).
 */
export function eventNameFilterFor(view: HeatmapViewType): Filter | null {
  switch (view) {
    case 'rage':
      return { property: 'event_name', operator: 'eq', value: 'rage_click' }
    case 'clicks':
    case 'movement':
      return { property: 'event_name', operator: 'eq', value: 'click' }
    case 'scroll':
      return null
  }
}

export interface TopElement {
  selector: string
  count: number
  /** Share of the total across all returned rows, 0–100. */
  sharePct: number
}

/**
 * Turn an `element_selector` breakdown into ranked "top elements" with each
 * row's share of the total interaction count. Rows with an empty selector are
 * collapsed under a single anonymous bucket so the list never shows blanks.
 */
export function topElementsFromBreakdown(
  rows: BreakdownRow[],
  countMetric: 'events' | 'pageviews' = 'events',
): TopElement[] {
  const counts = rows.map((row) => ({
    selector: row.value?.trim() ? row.value.trim() : '(unidentified)',
    count: row.metrics[countMetric] ?? 0,
  }))
  const total = counts.reduce((sum, row) => sum + row.count, 0)
  return counts
    .filter((row) => row.count > 0)
    .map((row) => ({
      ...row,
      sharePct: total > 0 ? (row.count / total) * 100 : 0,
    }))
    .sort((a, b) => b.count - a.count)
}

export interface ScrollDepthBar {
  depthPct: number
  sessionsPct: number
}

/**
 * Sort the scroll-depth distribution deepest-first (the wireframe lists 100% →
 * 25%) and clamp the session percentages to a sane 0–100 range. Missing data
 * yields an empty array so the panel can show its no-data state.
 */
export function scrollDepthBars(depths: HeatmapScrollDepth[] | undefined): ScrollDepthBar[] {
  if (!depths || depths.length === 0) return []
  return depths
    .map((entry) => ({
      depthPct: entry.depth_pct,
      sessionsPct: Math.min(100, Math.max(0, entry.sessions_pct)),
    }))
    .sort((a, b) => b.depthPct - a.depthPct)
}

/**
 * The fold line is the deepest bucket still reached by at least half the
 * sessions — a quick "where most people stop" marker for the scroll overlay.
 * Returns null when no bucket clears the threshold.
 */
export function foldDepthPct(bars: ScrollDepthBar[], threshold = 50): number | null {
  const reached = bars.filter((bar) => bar.sessionsPct >= threshold)
  if (reached.length === 0) return null
  return Math.max(...reached.map((bar) => bar.depthPct))
}

export interface PageListEntry {
  pathname: string
  clicks: number
  /** Width relative to the busiest page, 0–100, for the inline bar. */
  barPct: number
}

export type PageSort = 'clicks' | 'path'

/**
 * Build the searchable page list from a pathname breakdown: filter by the search
 * term, rank by the chosen sort, and size each inline bar against the busiest
 * page in the result set.
 */
export function buildPageList(
  rows: BreakdownRow[],
  search: string,
  sort: PageSort,
): PageListEntry[] {
  const term = search.trim().toLowerCase()
  const matched = rows
    .map((row) => ({ pathname: row.value, clicks: row.metrics.events ?? 0 }))
    .filter((row) => (term ? row.pathname.toLowerCase().includes(term) : true))

  const max = matched.reduce((peak, row) => Math.max(peak, row.clicks), 0)

  const ranked = [...matched].sort((a, b) =>
    sort === 'path' ? a.pathname.localeCompare(b.pathname) : b.clicks - a.clicks,
  )

  return ranked.map((row) => ({
    ...row,
    barPct: max > 0 ? (row.clicks / max) * 100 : 0,
  }))
}

/**
 * Derive sample size from the heatmap buckets. Each bucket's `count` is the
 * number of interactions it absorbed; their sum is the rendered sample.
 */
export function sampleSizeFromPoints(points: HeatmapClickPoint[]): number {
  return points.reduce((sum, point) => sum + point.count, 0)
}

export interface BehaviourInsight {
  kind: 'rage' | 'dead'
  selector: string
  count: number
  sharePct: number
}

/**
 * Rage-click / dead-click insight rows: the top offending selectors with their
 * share of the burst total. Used by the insight strip regardless of which
 * overlay is active so the warning is always visible.
 */
export function buildInsights(rows: BreakdownRow[], kind: 'rage' | 'dead'): BehaviourInsight[] {
  return topElementsFromBreakdown(rows).map((element) => ({
    kind,
    selector: element.selector,
    count: element.count,
    sharePct: element.sharePct,
  }))
}
