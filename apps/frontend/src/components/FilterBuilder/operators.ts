// =============================================================================
// Statflow Dashboard — Filter builder dimension/operator manifest
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Maps each filterable dimension to its value type and the operators valid for
// that type (components.md §3.10). The breakdown property list mirrors the
// dimensions the backend accepts on /analytics/{site}/breakdown.
// =============================================================================

import type { FilterOperator } from '@/api/types'

export type DimensionType = 'string' | 'number' | 'date'

export interface DimensionDef {
  property: string
  type: DimensionType
}

/** Filterable dimensions, aligned with the OpenAPI breakdown property list. */
export const DIMENSIONS: DimensionDef[] = [
  { property: 'pathname', type: 'string' },
  { property: 'referrer_domain', type: 'string' },
  { property: 'utm_source', type: 'string' },
  { property: 'utm_medium', type: 'string' },
  { property: 'utm_campaign', type: 'string' },
  { property: 'country', type: 'string' },
  { property: 'region', type: 'string' },
  { property: 'city', type: 'string' },
  { property: 'device_type', type: 'string' },
  { property: 'browser', type: 'string' },
  { property: 'os', type: 'string' },
  { property: 'language', type: 'string' },
  { property: 'entry_page', type: 'string' },
  { property: 'exit_page', type: 'string' },
  { property: 'event_name', type: 'string' },
]

const OPERATORS_BY_TYPE: Record<DimensionType, FilterOperator[]> = {
  string: ['eq', 'neq', 'contains', 'not_contains', 'starts_with', 'in', 'not_in'],
  number: ['eq', 'neq', 'gt', 'gte', 'lt', 'lte'],
  date: ['eq', 'gt', 'lt'],
}

export function operatorsFor(property: string): FilterOperator[] {
  const def = DIMENSIONS.find((d) => d.property === property)
  return OPERATORS_BY_TYPE[def?.type ?? 'string']
}

/** Operators whose value is a list (rendered as a multi-value input). */
export function isMultiValueOperator(operator: FilterOperator): boolean {
  return operator === 'in' || operator === 'not_in'
}
