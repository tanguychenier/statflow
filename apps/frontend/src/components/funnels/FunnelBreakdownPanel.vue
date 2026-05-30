<script setup lang="ts">
// =============================================================================
// FunnelBreakdownPanel — conversion-per-group bars (screens.md §3.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Lets the user pivot funnel conversion by device / country / source. The
// dimension selector drives the parent query; this component just frames the
// chart and the group-by control. Bar reshaping lives in funnelBreakdown.ts.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ChartCard, BarChart } from '@/components/charts'
import Select from '@/components/ui/select/Select.vue'
import {
  FUNNEL_BREAKDOWN_DIMENSIONS,
  type FunnelBreakdownDimension,
  type FunnelBarData,
} from './funnelBreakdown'

defineProps<{
  dimension: FunnelBreakdownDimension
  data: FunnelBarData
  loading?: boolean
  error?: boolean
  stale?: boolean
}>()

const emit = defineEmits<{
  'update:dimension': [value: FunnelBreakdownDimension]
  retry: []
}>()

const { t } = useI18n()

const dimensionOptions = computed(() =>
  FUNNEL_BREAKDOWN_DIMENSIONS.map((dim) => ({
    value: dim,
    label: t(`dimensions.${dim}`),
  })),
)
</script>

<template>
  <ChartCard :title="t('funnels.breakdown.title')">
    <template #actions>
      <label class="flex items-center gap-2 text-xs font-medium text-fg-secondary">
        {{ t('funnels.breakdown.groupBy') }}
        <Select
          :model-value="dimension"
          :options="dimensionOptions"
          @update:model-value="emit('update:dimension', $event as FunnelBreakdownDimension)"
        />
      </label>
    </template>
    <BarChart
      :title="t('funnels.breakdown.title')"
      :categories="data.categories"
      :values="data.values"
      :loading="loading"
      :error="error"
      :stale="stale"
      :height="280"
      @retry="emit('retry')"
    />
  </ChartCard>
</template>
