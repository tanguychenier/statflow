<script setup lang="ts">
// =============================================================================
// FilterBuilder — multi-condition segment / filter builder (components.md §3.10)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Edits an array of API `Filter` conditions plus the AND/OR group operator,
// v-modelled so the host (segment editor, funnel step, table toolbar) owns the
// state. Operators are contextual to the chosen dimension's type.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Plus } from 'lucide-vue-next'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import SegmentedControl from '@/components/ui/tabs/SegmentedControl.vue'
import Chip from '@/components/ui/badge/Chip.vue'
import { DIMENSIONS, operatorsFor, isMultiValueOperator } from './operators'
import type { Filter, FilterCombination, FilterOperator } from '@/api/types'

const props = defineProps<{ filters: Filter[]; combination: FilterCombination }>()

const emit = defineEmits<{
  'update:filters': [value: Filter[]]
  'update:combination': [value: FilterCombination]
}>()

const { t } = useI18n()

const dimensionOptions = computed(() =>
  DIMENSIONS.map((d) => ({ value: d.property, label: t(`dimensions.${d.property}`) })),
)

const combinationOptions = computed(() => [
  { value: 'and', label: 'AND' },
  { value: 'or', label: 'OR' },
])

function operatorOptions(property: string) {
  return operatorsFor(property).map((op) => ({ value: op, label: t(`operators.${op}`) }))
}

function update(index: number, patch: Partial<Filter>) {
  const next = props.filters.map((filter, i) => (i === index ? { ...filter, ...patch } : filter))
  emit('update:filters', next)
}

function addCondition() {
  emit('update:filters', [
    ...props.filters,
    { property: DIMENSIONS[0].property, operator: 'eq', value: '' },
  ])
}

function removeCondition(index: number) {
  emit('update:filters', props.filters.filter((_, i) => i !== index))
}

function onValueInput(index: number, raw: string, operator: FilterOperator) {
  const value = isMultiValueOperator(operator)
    ? raw.split(',').map((token) => token.trim()).filter(Boolean)
    : raw
  update(index, { value })
}

function valueAsText(value: Filter['value']): string {
  return Array.isArray(value) ? value.join(', ') : String(value ?? '')
}
</script>

<template>
  <div class="sf-filter-builder flex flex-col gap-2">
    <div class="flex items-center gap-2">
      <SegmentedControl
        :model-value="combination"
        :options="combinationOptions"
        :aria-label="t('common.filter')"
        @update:model-value="emit('update:combination', $event as FilterCombination)"
      />
    </div>

    <ul class="flex flex-col gap-2" role="list">
      <li
        v-for="(filter, index) in filters"
        :key="index"
        class="flex flex-wrap items-center gap-2 rounded-md border border-border bg-bg-surface p-2"
      >
        <Select
          :model-value="filter.property"
          :options="dimensionOptions"
          class="min-w-40"
          @update:model-value="update(index, { property: $event, operator: operatorsFor($event)[0] })"
        />
        <Select
          :model-value="filter.operator"
          :options="operatorOptions(filter.property)"
          class="min-w-32"
          @update:model-value="update(index, { operator: $event as FilterOperator })"
        />
        <Input
          :model-value="valueAsText(filter.value)"
          class="min-w-40 flex-1"
          :placeholder="t('common.search')"
          @update:model-value="onValueInput(index, $event, filter.operator)"
        />
        <Chip :label="t('common.delete')" @remove="removeCondition(index)" />
      </li>
    </ul>

    <Button variant="ghost" size="sm" class="self-start" @click="addCondition">
      <template #leading><Plus class="size-4" aria-hidden="true" /></template>
      {{ t('segments.addCondition') }}
    </Button>
  </div>
</template>
