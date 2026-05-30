<script setup lang="ts">
// =============================================================================
// LiveEventStream — pausable live event log (screens.md §4.1 / §4.2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Streams ingested events newest-first. Pause freezes the visible scroll while
// the parent keeps buffering; a "{n} new events" badge surfaces how many rows
// arrived while paused (the parent passes the paused-vs-live row sets). New rows
// fade/slide in via a TransitionGroup, disabled under reduced motion. Country is
// shown as a flag glyph for at-a-glance scanning; the code stays the a11y label.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Pause, Play } from 'lucide-vue-next'
import Card from '@/components/ui/card/Card.vue'
import Button from '@/components/ui/button/Button.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import EmptyState from '@/components/ui/empty-state/EmptyState.vue'
import { countryFlag, countryName } from '@/components/overview/countries'
import { useReducedMotion } from '@/composables/useReducedMotion'
import {
  environmentLabel,
  eventClock,
  eventTone,
  type EventTone,
  type LiveEventRow,
} from './realtimeModel'

const props = withDefaults(
  defineProps<{
    /** Rows to display (frozen snapshot while paused). */
    rows: LiveEventRow[]
    paused?: boolean
    /** Count of events buffered since the stream was paused. */
    bufferedCount?: number
    loading?: boolean
  }>(),
  { paused: false, bufferedCount: 0, loading: false },
)

const emit = defineEmits<{ 'toggle-pause': [] }>()

const { t } = useI18n()
const { prefersReduced } = useReducedMotion()

const TONE_VARIANT: Record<EventTone, 'success' | 'info' | 'accent' | 'default'> = {
  conversion: 'success',
  pageview: 'info',
  interaction: 'accent',
  default: 'default',
}

function toneVariant(eventName: string) {
  return TONE_VARIANT[eventTone(eventName)]
}

function flag(code: string): string {
  return countryFlag(code)
}

function countryLabel(code: string): string {
  return countryName(code)
}

const transitionName = computed(() => (prefersReduced.value || props.paused ? '' : 'rt-event'))
</script>

<template>
  <Card class="flex flex-col gap-4">
    <div class="flex items-center justify-between gap-3">
      <h2 class="m-0 text-base font-semibold text-fg-primary">{{ t('realtime.liveEvents') }}</h2>
      <div class="flex items-center gap-2">
        <Badge
          v-if="paused && bufferedCount > 0"
          variant="accent"
          aria-live="polite"
        >
          {{ t('realtime.newEvents', { count: bufferedCount }, bufferedCount) }}
        </Badge>
        <Button
          variant="outline"
          size="sm"
          :aria-pressed="paused"
          @click="emit('toggle-pause')"
        >
          <template #leading>
            <Play v-if="paused" class="size-4" aria-hidden="true" />
            <Pause v-else class="size-4" aria-hidden="true" />
          </template>
          {{ paused ? t('realtime.resume') : t('realtime.pause') }}
        </Button>
      </div>
    </div>

    <div class="rt-stream" role="log" aria-live="polite" :aria-busy="loading || undefined">
      <p
        v-if="loading && rows.length === 0"
        class="px-1 py-8 text-center text-sm text-fg-secondary"
      >
        {{ t('realtime.connecting') }}
      </p>

      <EmptyState
        v-else-if="rows.length === 0"
        variant="no-data"
        :title="t('realtime.empty.title')"
        :description="t('realtime.empty.description')"
      />

      <TransitionGroup v-else tag="ul" :name="transitionName" class="flex flex-col">
        <li
          v-for="row in rows"
          :key="row.id"
          class="rt-event-row grid items-center gap-3 border-b border-border px-1 py-2 last:border-b-0"
        >
          <time class="font-mono text-xs tabular-nums text-fg-muted" :datetime="row.timestamp">
            {{ eventClock(row.timestamp) }}
          </time>
          <Badge :variant="toneVariant(row.eventName)">{{ row.eventName }}</Badge>
          <span class="truncate font-mono text-sm text-fg-primary" :title="row.pathname">
            {{ row.pathname }}
          </span>
          <span class="truncate text-xs text-fg-secondary" :title="environmentLabel(row.browser, row.device)">
            {{ environmentLabel(row.browser, row.device) }}
          </span>
          <span class="justify-self-end text-sm" :aria-label="countryLabel(row.country)">
            <span aria-hidden="true">{{ flag(row.country) || row.country }}</span>
          </span>
        </li>
      </TransitionGroup>
    </div>
  </Card>
</template>

<style scoped>
.rt-stream {
  position: relative;
  max-height: 420px;
  overflow-y: auto;
}

.rt-event-row {
  grid-template-columns: auto auto minmax(0, 1.4fr) minmax(0, 1fr) auto;
}

.rt-event-enter-active {
  transition:
    opacity var(--sf-duration-base) var(--sf-ease-out),
    transform var(--sf-duration-base) var(--sf-ease-out);
}

.rt-event-enter-from {
  opacity: 0;
  transform: translateY(-6px);
}

.rt-event-move {
  transition: transform var(--sf-duration-base) var(--sf-ease-out);
}

@media (width < 768px) {
  .rt-event-row {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }

  /* Hide the environment column on mobile to keep rows scannable. */
  .rt-event-row > :nth-child(2),
  .rt-event-row > :nth-child(4) {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .rt-event-enter-active,
  .rt-event-move {
    transition: none;
  }
}
</style>
