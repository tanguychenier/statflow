<script setup lang="ts">
// =============================================================================
// FunnelListItem — one funnel card in the list view (screens.md §3.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The whole card opens the funnel detail; the Edit/Delete buttons stop event
// propagation so they act in place. Conversion shows a dash until the funnel
// has been queried (it is unknown from the saved definition alone).
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Pencil, Trash2 } from 'lucide-vue-next'
import Card from '@/components/ui/card/Card.vue'
import Button from '@/components/ui/button/Button.vue'
import { useRelativeTime } from '@/composables/useRelativeTime'
import type { FunnelListEntry } from './funnelStats'

const props = defineProps<{ entry: FunnelListEntry }>()

const emit = defineEmits<{ open: []; edit: []; remove: [] }>()

const { t, n } = useI18n()
const relativeTime = useRelativeTime()

const conversionLabel = computed(() =>
  props.entry.overallConversionPct === null
    ? '—'
    : n(props.entry.overallConversionPct / 100, 'percent'),
)

const lastEdited = computed(() => relativeTime.format(props.entry.funnel.updated_at))

function onEdit(event: Event) {
  event.stopPropagation()
  emit('edit')
}

function onRemove(event: Event) {
  event.stopPropagation()
  emit('remove')
}
</script>

<template>
  <Card class="relative transition-colors hover:border-border-strong">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex min-w-0 flex-col gap-1">
        <h2 class="truncate text-sm font-semibold text-fg-primary">
          <!-- Stretched button covers the card surface without wrapping the
              Edit/Delete controls, so there is no nested-interactive a11y issue. -->
          <button
            type="button"
            class="text-left after:absolute after:inset-0 after:content-[''] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus"
            :aria-label="t('funnels.openFunnel', { name: entry.funnel.name })"
            @click="emit('open')"
          >
            {{ entry.funnel.name }}
          </button>
        </h2>
        <p class="text-xs text-fg-muted">{{ t('funnels.lastEdited', { time: lastEdited }) }}</p>
      </div>

      <div class="relative z-10 flex items-center gap-6">
        <div class="flex flex-col items-end">
          <span class="text-xs text-fg-muted">
            {{ t('funnels.stepsCount', { count: entry.stepCount }, entry.stepCount) }}
          </span>
          <span class="text-sm font-semibold text-fg-primary">
            {{ t('funnels.overallShort', { pct: conversionLabel }) }}
          </span>
        </div>

        <div class="flex items-center gap-1">
          <Button
            variant="ghost"
            size="icon-sm"
            :aria-label="t('funnels.editFunnel')"
            @click="onEdit"
          >
            <Pencil class="size-4" aria-hidden="true" />
          </Button>
          <Button
            variant="ghost"
            size="icon-sm"
            :aria-label="t('funnels.deleteFunnel')"
            @click="onRemove"
          >
            <Trash2 class="size-4" aria-hidden="true" />
          </Button>
        </div>
      </div>
    </div>
  </Card>
</template>
