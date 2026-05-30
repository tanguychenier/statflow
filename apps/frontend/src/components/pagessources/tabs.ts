// =============================================================================
// Pages & Sources — tab + column configuration (screens.md §5)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Each tab maps to one breakdown dimension plus the set of metric columns the
// table shows for it. Keeping this as plain data (no Vue) makes the screen a
// declarative composition and lets the URL ↔ tab sync stay trivially testable.
// =============================================================================

import type { BreakdownProperty, MetricName } from '@/api/types'

/** Stable tab identifiers — also the value written to `?tab=` in the URL. */
export type PagesSourcesTab =
  | 'pages'
  | 'entry'
  | 'exit'
  | 'sources'
  | 'referrers'
  | 'utm'

/** UTM is split into three sub-dimensions, each its own table. */
export type UtmSubTab = 'utm_campaign' | 'utm_medium' | 'utm_source'

/** How a metric column is rendered in a cell. */
export type MetricFormat = 'count' | 'percent' | 'duration'

export interface MetricColumn {
  metric: MetricName
  /** i18n key for the column header. */
  labelKey: string
  format: MetricFormat
}

export interface TabConfig {
  id: PagesSourcesTab
  /** i18n key for the tab trigger label. */
  labelKey: string
  /** Breakdown dimension queried for this tab. */
  property: BreakdownProperty
  /** i18n key for the leading dimension column header. */
  dimensionLabelKey: string
  /** i18n key for the row search input placeholder. */
  searchPlaceholderKey: string
}

/**
 * The metric columns every table shares. The first entry is the default sort
 * column and the one whose value drives the inline bar.
 */
export const METRIC_COLUMNS: MetricColumn[] = [
  { metric: 'visitors', labelKey: 'pagesSources.metrics.visitors', format: 'count' },
  { metric: 'pageviews', labelKey: 'pagesSources.metrics.pageviews', format: 'count' },
  { metric: 'bounce_rate', labelKey: 'pagesSources.metrics.bounceRate', format: 'percent' },
  { metric: 'avg_duration', labelKey: 'pagesSources.metrics.avgDuration', format: 'duration' },
]

/** Metrics requested from the API for every table (ranking + all columns). */
export const REQUESTED_METRICS: MetricName[] = METRIC_COLUMNS.map((column) => column.metric)

export const TABS: TabConfig[] = [
  {
    id: 'pages',
    labelKey: 'pagesSources.tabs.pages',
    property: 'pathname',
    dimensionLabelKey: 'dimensions.pathname',
    searchPlaceholderKey: 'pagesSources.search.pages',
  },
  {
    id: 'entry',
    labelKey: 'pagesSources.tabs.entryPages',
    property: 'entry_page',
    dimensionLabelKey: 'dimensions.entry_page',
    searchPlaceholderKey: 'pagesSources.search.pages',
  },
  {
    id: 'exit',
    labelKey: 'pagesSources.tabs.exitPages',
    property: 'exit_page',
    dimensionLabelKey: 'dimensions.exit_page',
    searchPlaceholderKey: 'pagesSources.search.pages',
  },
  {
    id: 'sources',
    labelKey: 'pagesSources.tabs.sources',
    property: 'utm_source',
    dimensionLabelKey: 'dimensions.utm_source',
    searchPlaceholderKey: 'pagesSources.search.sources',
  },
  {
    id: 'referrers',
    labelKey: 'pagesSources.tabs.referrers',
    property: 'referrer_domain',
    dimensionLabelKey: 'dimensions.referrer_domain',
    searchPlaceholderKey: 'pagesSources.search.referrers',
  },
  {
    id: 'utm',
    labelKey: 'pagesSources.tabs.utm',
    property: 'utm_campaign',
    dimensionLabelKey: 'dimensions.utm_campaign',
    searchPlaceholderKey: 'pagesSources.search.utm',
  },
]

export const UTM_SUBTABS: UtmSubTab[] = ['utm_campaign', 'utm_medium', 'utm_source']

const TAB_IDS = new Set<string>(TABS.map((tab) => tab.id))
const UTM_SUBTAB_IDS = new Set<string>(UTM_SUBTABS)

/** Resolve a raw `?tab=` value to a known tab, defaulting to "pages". */
export function resolveTab(raw: unknown): PagesSourcesTab {
  return typeof raw === 'string' && TAB_IDS.has(raw) ? (raw as PagesSourcesTab) : 'pages'
}

/** Resolve a raw `?utm=` value to a known UTM sub-tab, defaulting to campaign. */
export function resolveUtmSubTab(raw: unknown): UtmSubTab {
  return typeof raw === 'string' && UTM_SUBTAB_IDS.has(raw) ? (raw as UtmSubTab) : 'utm_campaign'
}

export function tabConfig(id: PagesSourcesTab): TabConfig {
  return TABS.find((tab) => tab.id === id) ?? TABS[0]
}

/** Map a UTM sub-tab to the breakdown property it queries. */
export function utmProperty(sub: UtmSubTab): BreakdownProperty {
  return sub
}

/** Map a UTM sub-tab to the i18n key for its dimension column header. */
export function utmDimensionKey(sub: UtmSubTab): string {
  return `dimensions.${sub}`
}
