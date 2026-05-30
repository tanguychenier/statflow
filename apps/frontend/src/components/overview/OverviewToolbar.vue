<script setup lang="ts">
// =============================================================================
// OverviewToolbar — screen-level controls (screens.md §1.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The global date range lives in the topbar; this toolbar carries the controls
// that are specific to the Overview chart: granularity (Daily/Weekly…), the
// compare-to-previous-period toggle, and the CSV export action. All state is
// owned by the parent / date-range store and surfaced through events.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Download } from 'lucide-vue-next'
import SegmentedControl from '@/components/ui/tabs/SegmentedControl.vue'
import Switch from '@/components/ui/switch/Switch.vue'
import Button from '@/components/ui/button/Button.vue'
import type { TimeInterval } from '@/api/types'

const props = defineProps<{
  interval: TimeInterval
  compareEnabled: boolean
  exporting?: boolean
}>()

const emit = defineEmits<{
  'update:interval': [value: TimeInterval]
  'update:compare': [value: boolean]
  export: []
}>()

const { t } = useI18n()

const INTERVAL_KEYS: Record<string, string> = {
  hour: 'overview.granularity.hourly',
  day: 'overview.granularity.daily',
  week: 'overview.granularity.weekly',
  month: 'overview.granularity.monthly',
}

const intervalOptions = computed(() =>
  (['hour', 'day', 'week', 'month'] as TimeInterval[]).map((value) => ({
    value,
    label: t(INTERVAL_KEYS[value]),
  })),
)

// SegmentedControl is string-typed; cast back to the TimeInterval union on emit.
function onInterval(value: string) {
  emit('update:interval', value as TimeInterval)
}

const compareOn = computed(() => props.compareEnabled)
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3">
    <SegmentedControl
      :model-value="interval"
      :options="intervalOptions"
      :aria-label="t('overview.toolbar.granularity')"
      @update:model-value="onInterval"
    />

    <div class="flex items-center gap-4">
      <label class="flex items-center gap-2 text-sm text-fg-secondary" for="sf-overview-compare">
        {{ t('overview.toolbar.compare') }}
        <Switch
          id="sf-overview-compare"
          :model-value="compareOn"
          :aria-label="t('overview.toolbar.compare')"
          @update:model-value="emit('update:compare', $event)"
        />
      </label>

      <Button
        variant="outline"
        size="sm"
        :loading="exporting"
        :aria-label="t('overview.toolbar.export')"
        @click="emit('export')"
      >
        <template #leading><Download class="size-4" aria-hidden="true" /></template>
        {{ t('common.export') }}
      </Button>
    </div>
  </div>
</template>
