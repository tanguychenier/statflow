<script setup lang="ts">
// =============================================================================
// FunnelStepRow — one editable step in the funnel builder (screens.md §3.3)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Owns no state of its own: it renders a DraftStep and emits a patched copy so
// the parent (FunnelBuilderModal) remains the single source of truth. The
// trigger toggle swaps which matcher field (URL pattern vs. event name) is
// edited; the optional Conditions section reuses the shared FilterBuilder.
// =============================================================================
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ChevronUp, ChevronDown, X, SlidersHorizontal } from 'lucide-vue-next'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import SegmentedControl from '@/components/ui/tabs/SegmentedControl.vue'
import FilterBuilder from '@/components/FilterBuilder/FilterBuilder.vue'
import type { Filter, FunnelTriggerType } from '@/api/types'
import type { DraftStep } from './funnelBuilder'

const props = defineProps<{
  step: DraftStep
  /** 1-based ordinal shown to users; 0-based internally. */
  position: number
  invalid?: boolean
  canMoveUp: boolean
  canMoveDown: boolean
  canRemove: boolean
}>()

const emit = defineEmits<{
  update: [patch: Partial<DraftStep>]
  remove: []
  move: [direction: -1 | 1]
}>()

const { t } = useI18n()

const showConditions = ref(props.step.filters.length > 0)

const triggerOptions = computed(() => [
  { value: 'pageview', label: t('funnels.builder.triggers.pageview') },
  { value: 'event', label: t('funnels.builder.triggers.event') },
])

const matcherValue = computed(() =>
  props.step.triggerType === 'pageview' ? props.step.urlPattern : props.step.eventName,
)

const matcherPlaceholder = computed(() =>
  props.step.triggerType === 'pageview'
    ? t('funnels.builder.urlPlaceholder')
    : t('funnels.builder.eventPlaceholder'),
)

function onTrigger(value: string) {
  emit('update', { triggerType: value as FunnelTriggerType })
}

function onMatcher(value: string) {
  if (props.step.triggerType === 'pageview') emit('update', { urlPattern: value })
  else emit('update', { eventName: value })
}

function onFilters(filters: Filter[]) {
  emit('update', { filters })
}

function toggleConditions() {
  showConditions.value = !showConditions.value
}
</script>

<template>
  <li class="flex flex-col gap-3 rounded-lg border border-border bg-bg-surface p-3">
    <div class="flex items-start gap-3">
      <span
        class="mt-1.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-bg-subtle text-xs font-semibold text-fg-secondary"
        aria-hidden="true"
      >
        {{ position }}
      </span>

      <div class="flex min-w-0 flex-1 flex-col gap-2">
        <div class="flex flex-wrap items-center gap-2">
          <SegmentedControl
            :model-value="step.triggerType"
            :options="triggerOptions"
            :aria-label="t('funnels.builder.eventType')"
            @update:model-value="onTrigger"
          />
          <Input
            :model-value="matcherValue"
            :placeholder="matcherPlaceholder"
            :error="invalid"
            :aria-label="t('funnels.builder.matcher', { n: position })"
            class="min-w-0 flex-1"
            @update:model-value="onMatcher"
          />
        </div>

        <Input
          :model-value="step.label"
          :placeholder="t('funnels.builder.labelPlaceholder')"
          :aria-label="t('funnels.builder.stepLabel', { n: position })"
          @update:model-value="emit('update', { label: $event })"
        />

        <button
          type="button"
          class="inline-flex w-fit items-center gap-1.5 text-xs font-medium text-fg-secondary hover:text-fg-primary"
          :aria-expanded="showConditions"
          @click="toggleConditions"
        >
          <SlidersHorizontal class="size-3.5" aria-hidden="true" />
          {{ t('funnels.builder.conditions') }}
          <span v-if="step.filters.length" class="text-fg-muted">({{ step.filters.length }})</span>
        </button>

        <FilterBuilder
          v-if="showConditions"
          :filters="step.filters"
          combination="and"
          @update:filters="onFilters"
        />
      </div>

      <div class="flex shrink-0 flex-col gap-1">
        <Button
          variant="ghost"
          size="icon"
          :disabled="!canMoveUp"
          :aria-label="t('funnels.builder.moveUp')"
          @click="emit('move', -1)"
        >
          <ChevronUp class="size-4" aria-hidden="true" />
        </Button>
        <Button
          variant="ghost"
          size="icon"
          :disabled="!canMoveDown"
          :aria-label="t('funnels.builder.moveDown')"
          @click="emit('move', 1)"
        >
          <ChevronDown class="size-4" aria-hidden="true" />
        </Button>
        <Button
          variant="ghost"
          size="icon"
          :disabled="!canRemove"
          :aria-label="t('funnels.builder.removeStep', { n: position })"
          @click="emit('remove')"
        >
          <X class="size-4" aria-hidden="true" />
        </Button>
      </div>
    </div>
  </li>
</template>
