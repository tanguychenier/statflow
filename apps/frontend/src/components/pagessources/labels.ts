// =============================================================================
// Pages & Sources — dimension value display helpers
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The breakdown API returns raw dimension values; some need a friendly label.
// An empty referrer domain or UTM value means "direct"/"none" traffic, and we
// surface that with a translated placeholder rather than a blank cell.
// =============================================================================

import type { PagesSourcesTab, UtmSubTab } from './tabs'

/** Tabs whose empty/unknown dimension value should read as "Direct". */
const DIRECT_TABS = new Set<PagesSourcesTab>(['sources', 'referrers'])

/**
 * Resolve the display label for a row value. `tFn` supplies the translated
 * placeholders so this stays locale-agnostic and unit-testable.
 */
export function displayLabel(
  value: string,
  tab: PagesSourcesTab,
  tFn: (key: string) => string,
): string {
  if (value !== '') return value
  return DIRECT_TABS.has(tab) ? tFn('pagesSources.direct') : tFn('pagesSources.none')
}

/** Display label for a UTM sub-tab row; empty values read as "(none)". */
export function utmDisplayLabel(value: string, tFn: (key: string) => string): string {
  return value === '' ? tFn('pagesSources.none') : value
}

export type { UtmSubTab }
