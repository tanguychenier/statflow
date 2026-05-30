// =============================================================================
// Public shared dashboard — period parsing helper
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The public response carries the owner-configured period as `YYYY-MM-DD`
// strings. This turns that DTO into a pair of Dates the view can hand to
// vue-i18n's `d()` for locale-aware display (screens.md §8.1, "display only").
// Parsing is done at local noon to keep the rendered day stable across time
// zones, and invalid input collapses to null so the header simply omits the
// period rather than rendering "Invalid Date".
// =============================================================================

import type { DateRangeDto } from '@/api/types'

export interface PublicPeriod {
  from: Date
  to: Date
}

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/

/** Parse a `YYYY-MM-DD` string to a local-noon Date, or null when malformed. */
export function parseIsoDate(value: string | null | undefined): Date | null {
  if (!value || !ISO_DATE.test(value)) return null
  const [year, month, day] = value.split('-').map(Number)
  const date = new Date(year, month - 1, day, 12, 0, 0, 0)
  if (Number.isNaN(date.getTime())) return null
  // Reject calendar overflow (e.g. 2024-02-31 rolling into March).
  if (date.getMonth() !== month - 1 || date.getDate() !== day) return null
  return date
}

/** Turn the response period DTO into a Date pair, or null when either bound is
 *  missing/invalid (the header then shows no period). */
export function parsePublicPeriod(period: DateRangeDto | null | undefined): PublicPeriod | null {
  if (!period) return null
  const from = parseIsoDate(period.from)
  const to = parseIsoDate(period.to)
  if (!from || !to) return null
  return { from, to }
}
