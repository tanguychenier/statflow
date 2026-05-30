<script setup lang="ts">
// =============================================================================
// PagesSourcesToolbar — per-table controls (screens.md §5.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The row of controls above each table: a debounced search field (server search
// fires on Enter, client filtering as you type), a metric selector that also
// becomes the primary sort column, and the CSV export action. All state is owned
// by the parent view and surfaced through events.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Download, Search } from 'lucide-vue-next'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Button from '@/components/ui/button/Button.vue'
import { METRIC_COLUMNS } from './tabs'
import type { MetricName } from '@/api/types'

const props = defineProps<{
  search: string
  searchPlaceholder: string
  metric: MetricName
  exporting?: boolean
}>()

const emit = defineEmits<{
  'update:search': [value: string]
  'update:metric': [value: MetricName]
  submitSearch: []
  export: []
}>()

const { t } = useI18n()

const metricOptions = computed(() =>
  METRIC_COLUMNS.map((column) => ({ value: column.metric, label: t(column.labelKey) })),
)

const metricValue = computed(() => props.metric)

function onMetric(value: string) {
  emit('update:metric', value as MetricName)
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3">
    <div class="min-w-48 flex-1">
      <Input
        :model-value="search"
        type="search"
        :placeholder="searchPlaceholder"
        :aria-label="searchPlaceholder"
        @update:model-value="emit('update:search', $event)"
        @keydown.enter="emit('submitSearch')"
      >
        <template #leading><Search class="size-4" aria-hidden="true" /></template>
      </Input>
    </div>

    <label class="flex items-center gap-2 text-sm text-fg-secondary">
      <span class="whitespace-nowrap">{{ t('pagesSources.toolbar.metric') }}</span>
      <Select
        :model-value="metricValue"
        :options="metricOptions"
        :aria-label="t('pagesSources.toolbar.metric')"
        @update:model-value="onMetric"
      />
    </label>

    <Button
      variant="outline"
      size="sm"
      :loading="exporting"
      :aria-label="t('pagesSources.toolbar.export')"
      @click="emit('export')"
    >
      <template #leading><Download class="size-4" aria-hidden="true" /></template>
      {{ t('common.export') }}
    </Button>
  </div>
</template>
