<script setup lang="ts">
// =============================================================================
// FunnelSummaryStats — detail-view header figures (screens.md §3.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Three at-a-glance stats above the funnel chart. Skeletons while loading keep
// the layout from jumping; the time-to-convert stat is hidden when the backend
// reported no timing data rather than showing a misleading "0s".
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Skeleton } from '@/components/ui/skeleton'
import { formatDuration } from '@/utils/formatDuration'
import type { FunnelSummary } from './funnelStats'

const props = withDefaults(
  defineProps<{ summary: FunnelSummary; loading?: boolean }>(),
  { loading: false },
)

const { t, n } = useI18n()

const conversion = computed(() => n(props.summary.overallConversionPct / 100, 'percent'))
const entered = computed(() => n(props.summary.totalEntered, 'integer'))
const timeToConvert = computed(() =>
  props.summary.totalTimeToConvertSeconds === null
    ? null
    : formatDuration(props.summary.totalTimeToConvertSeconds),
)
</script>

<template>
  <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="flex flex-col gap-1">
      <dt class="text-xs font-medium text-fg-secondary">{{ t('funnels.overallConversion') }}</dt>
      <Skeleton v-if="loading" class="h-7 w-20" />
      <dd v-else class="text-2xl font-semibold tabular-nums text-fg-primary">{{ conversion }}</dd>
    </div>

    <div class="flex flex-col gap-1">
      <dt class="text-xs font-medium text-fg-secondary">{{ t('funnels.totalEntered') }}</dt>
      <Skeleton v-if="loading" class="h-7 w-24" />
      <dd v-else class="text-2xl font-semibold tabular-nums text-fg-primary">
        {{ t('funnels.enteredSessions', { count: entered }) }}
      </dd>
    </div>

    <div v-if="loading || timeToConvert" class="flex flex-col gap-1">
      <dt class="text-xs font-medium text-fg-secondary">{{ t('funnels.medianTime') }}</dt>
      <Skeleton v-if="loading" class="h-7 w-16" />
      <dd v-else class="text-2xl font-semibold tabular-nums text-fg-primary">{{ timeToConvert }}</dd>
    </div>
  </dl>
</template>
