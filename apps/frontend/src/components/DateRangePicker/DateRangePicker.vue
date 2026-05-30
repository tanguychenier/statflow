<script setup lang="ts">
// =============================================================================
// DateRangePicker — global/local date range selector (components.md §3.9)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Trigger shows the formatted active range; the popover offers preset chips,
// manual date inputs (formatted via vue-i18n `d`), and a compare toggle. Wired
// to the date-range Pinia store so changing the range invalidates analytics
// queries app-wide. Keyboard order follows accessibility.md §3.3.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { CalendarDays } from 'lucide-vue-next'
import Popover from '@/components/ui/popover/Popover.vue'
import Button from '@/components/ui/button/Button.vue'
import Switch from '@/components/ui/switch/Switch.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import { useDateRangeStore, type DateRangePreset, toIsoDate } from '@/stores/dateRange'
import type { ComparePeriod } from '@/api/types'

const { t, d } = useI18n()
const store = useDateRangeStore()
const { range, preset, compareEnabled, comparePeriod } = storeToRefs(store)

const open = ref(false)

const PRESETS: DateRangePreset[] = [
  'today',
  'yesterday',
  'last7Days',
  'last30Days',
  'last90Days',
  'thisMonth',
  'lastMonth',
]

const triggerLabel = computed(() => `${d(range.value.from, 'short')} – ${d(range.value.to, 'short')}`)

const customFrom = ref(toIsoDate(range.value.from))
const customTo = ref(toIsoDate(range.value.to))

const compareOptions = computed(() => [
  { value: 'previous_period', label: t('dateRange.previousPeriod') },
  { value: 'previous_year', label: t('dateRange.previousYear') },
])

function selectPreset(next: DateRangePreset) {
  store.setPreset(next)
  customFrom.value = toIsoDate(range.value.from)
  customTo.value = toIsoDate(range.value.to)
}

function applyCustom() {
  const from = new Date(`${customFrom.value}T00:00:00`)
  const to = new Date(`${customTo.value}T23:59:59`)
  if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime()) || from > to) return
  store.setCustomRange(from, to)
}
</script>

<template>
  <Popover v-model:open="open" align="end" class="w-[340px]">
    <template #trigger>
      <Button variant="outline" size="sm" aria-haspopup="dialog" :aria-expanded="open">
        <template #leading><CalendarDays class="size-4" aria-hidden="true" /></template>
        {{ triggerLabel }}
      </Button>
    </template>

    <div class="flex flex-col gap-3">
      <div class="grid grid-cols-2 gap-1" role="group" :aria-label="t('common.filter')">
        <button
          v-for="p in PRESETS"
          :key="p"
          type="button"
          class="rounded-md px-2 py-1.5 text-left text-xs transition-colors hover:bg-bg-subtle"
          :class="preset === p ? 'bg-accent-subtle text-accent-text' : 'text-fg-secondary'"
          @click="selectPreset(p)"
        >
          {{ t(`dateRange.${p}`) }}
        </button>
      </div>

      <div class="flex items-end gap-2 border-t border-border pt-3">
        <label class="flex-1 text-xs text-fg-secondary">
          {{ t('dateRange.custom') }}
          <Input v-model="customFrom" type="date" class="mt-1" />
        </label>
        <label class="flex-1 text-xs text-fg-secondary">
          &nbsp;
          <Input v-model="customTo" type="date" class="mt-1" />
        </label>
        <Button size="sm" @click="applyCustom">{{ t('common.confirm') }}</Button>
      </div>

      <div class="flex items-center justify-between border-t border-border pt-3">
        <label class="text-xs text-fg-secondary" for="sf-compare-toggle">
          {{ t('dateRange.compare') }}
        </label>
        <Switch
          id="sf-compare-toggle"
          :model-value="compareEnabled"
          :aria-label="t('dateRange.compare')"
          @update:model-value="store.toggleCompare()"
        />
      </div>
      <Select
        v-if="compareEnabled"
        :model-value="comparePeriod"
        :options="compareOptions"
        @update:model-value="store.setComparePeriod($event as ComparePeriod)"
      />
    </div>
  </Popover>
</template>
