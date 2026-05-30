// =============================================================================
// Funnels — derive a conversion filter from a funnel's final step
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The breakdown / trend panels reuse the generic analytics endpoints scoped to
// the funnel's *conversion event* — i.e. its last step. This translates that
// step into the `Filter` the breakdown/timeseries queries carry so "conversion
// per group" and "conversion over time" reflect the funnel's goal, not all
// traffic. Pure → unit-tested.
// =============================================================================

import type { Filter, Funnel } from '@/api/types'

/**
 * Build a single filter matching a funnel's final step. Pageview steps filter on
 * `pathname`, event steps on `event_name`. Returns null when the funnel has no
 * usable final-step matcher, so callers can skip the scoped query.
 */
export function goalFilterForFunnel(funnel: Funnel | null | undefined): Filter | null {
  if (!funnel || funnel.steps.length === 0) return null
  const last = [...funnel.steps].sort((a, b) => a.step_index - b.step_index).at(-1)
  if (!last) return null

  if (last.trigger_type === 'pageview' && last.url_pattern) {
    return { property: 'pathname', operator: 'eq', value: last.url_pattern }
  }
  if (last.trigger_type === 'event' && last.event_name) {
    return { property: 'event_name', operator: 'eq', value: last.event_name }
  }
  return null
}
