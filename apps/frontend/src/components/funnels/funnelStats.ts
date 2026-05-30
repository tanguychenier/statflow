// =============================================================================
// Funnels — summary & per-step view-model builders (screens.md §3.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Pure reshaping of a FunnelResponse into the values the detail header and the
// FunnelChart render. Keeping the conversion / drop-off arithmetic here (rather
// than in the .vue) makes it deterministic and unit-testable without mounting a
// component or hitting the analytics API.
// =============================================================================

import type { Funnel, FunnelResponse, FunnelResponseStep } from '@/api/types'

export interface FunnelSummary {
  /** Users who entered the first step. */
  totalEntered: number
  /** End-to-end conversion, as a 0–100 percentage (0 when no entrants). */
  overallConversionPct: number
  /**
   * Average seconds to traverse the whole funnel (sum of per-step averages).
   * `null` when the backend reported no timing data so the caller can hide it.
   */
  totalTimeToConvertSeconds: number | null
}

/**
 * Derive the three header figures from a funnel response. The overall rate is
 * taken from the last step's cumulative `conversion_rate_pct` when present,
 * otherwise recomputed from entered/converted counts so the header still shows a
 * sane value if the backend omits the cumulative field.
 */
export function buildFunnelSummary(response: FunnelResponse | null | undefined): FunnelSummary {
  const steps = response?.steps ?? []
  const first = steps[0]
  const last = steps[steps.length - 1]
  const totalEntered = first?.entered ?? 0

  let overallConversionPct = 0
  if (last && totalEntered > 0) {
    overallConversionPct =
      typeof last.conversion_rate_pct === 'number'
        ? last.conversion_rate_pct
        : (last.converted / totalEntered) * 100
  }

  const timed = steps.filter((step) => step.avg_time_to_convert_seconds > 0)
  const totalTimeToConvertSeconds = timed.length
    ? timed.reduce((sum, step) => sum + step.avg_time_to_convert_seconds, 0)
    : null

  return { totalEntered, overallConversionPct, totalTimeToConvertSeconds }
}

export interface FunnelStepView {
  index: number
  label: string
  entered: number
  /** Share of first-step entrants reaching this step (0–100). */
  fromEntryPct: number
  /** Share of the *previous* step that continued (0–100); null for step 0. */
  continuedPct: number | null
  /** Share of the *previous* step that dropped off (0–100); null for step 0. */
  droppedPct: number | null
}

/**
 * Build the per-step rows used by the detail view's annotations. Continued and
 * dropped percentages are relative to the *previous* step (the funnel mental
 * model), while `fromEntryPct` is cumulative against the first step.
 */
export function buildStepViews(steps: FunnelResponseStep[]): FunnelStepView[] {
  const entry = steps[0]?.entered ?? 0
  return steps.map((step, i) => {
    const prev = steps[i - 1]
    const fromEntryPct = entry > 0 ? (step.entered / entry) * 100 : 0
    let continuedPct: number | null = null
    let droppedPct: number | null = null
    if (prev && prev.entered > 0) {
      continuedPct = (step.entered / prev.entered) * 100
      droppedPct = 100 - continuedPct
    }
    return {
      index: step.step_index,
      label: step.label,
      entered: step.entered,
      fromEntryPct,
      continuedPct,
      droppedPct,
    }
  })
}

/**
 * The end-to-end conversion of a *saved* funnel definition is unknown until it
 * is queried, so the list view shows a placeholder until a response arrives.
 * This pairs a definition with its (optional) latest response for list display.
 */
export interface FunnelListEntry {
  funnel: Funnel
  stepCount: number
  /** Overall conversion once queried, else null. */
  overallConversionPct: number | null
}

export function buildListEntry(
  funnel: Funnel,
  response?: FunnelResponse | null,
): FunnelListEntry {
  return {
    funnel,
    stepCount: funnel.steps.length,
    overallConversionPct: response ? buildFunnelSummary(response).overallConversionPct : null,
  }
}
