// =============================================================================
// Statflow Dashboard — useChartFormat composable
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Builds the locale-aware ChartFormatters object the option builders need
// (i18n.md §7). Numbers go through vue-i18n's `n` with the `compact` format;
// dates through `d` with the `monthShort` format.
// =============================================================================

import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ChartFormatters } from '@/charts/options'

export function useChartFormat() {
  const { n, d } = useI18n()

  const formatters = computed<ChartFormatters>(() => ({
    value: (value: number) => n(value, 'compact'),
    axisDate: (label: string) => {
      const date = new Date(label)
      return Number.isNaN(date.getTime()) ? label : d(date, 'monthShort')
    },
  }))

  return { formatters }
}
