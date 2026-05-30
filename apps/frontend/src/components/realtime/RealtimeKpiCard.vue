<script setup lang="ts">
// =============================================================================
// RealtimeKpiCard — compact live KPI tile (screens.md §4.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// A small surface showing one live metric. When `pulse` is set the value glows
// briefly on every change (the "Active users" count pulses on update, §4.2);
// the glow is suppressed under prefers-reduced-motion. Purely presentational —
// the parent supplies the already-formatted value and decides what pulses.
//
// When `live` is set the tile carries a polite, atomic live region for the
// headline active-user count. Sighted users see every update immediately, but
// the announcement is throttled so a screen reader is not flooded by the ~2s
// stats cadence — at most one announcement per `liveThrottleMs` (accessibility
// .md §4.3).
// =============================================================================
import { computed, onUnmounted, ref, watch } from 'vue'
import { useReducedMotion } from '@/composables/useReducedMotion'

const props = withDefaults(
  defineProps<{
    label: string
    value: string
    /** Glow the value on each change (used for the headline active-user count). */
    pulse?: boolean
    /** Render the value in monospace (paths, throughput). */
    mono?: boolean
    loading?: boolean
    /** Announce value changes through a polite, atomic live region. */
    live?: boolean
    /** Minimum gap between announcements, in ms (default ~5s). */
    liveThrottleMs?: number
  }>(),
  { pulse: false, mono: false, loading: false, live: false, liveThrottleMs: 5_000 },
)

const { prefersReduced } = useReducedMotion()
const pulsing = ref(false)

watch(
  () => props.value,
  () => {
    if (!props.pulse || prefersReduced.value) return
    pulsing.value = false
    // Re-trigger the keyframe by toggling on the next frame.
    requestAnimationFrame(() => {
      pulsing.value = true
    })
  },
)

function onPulseEnd() {
  pulsing.value = false
}

// ── Throttled live announcement ──────────────────────────────────────────────
const announced = ref('')
const announcement = computed(() => (props.loading ? '' : `${props.label}: ${props.value}`))
let lastAnnouncedAt = 0
let pendingTimer: ReturnType<typeof setTimeout> | null = null

function flushAnnouncement() {
  announced.value = announcement.value
  lastAnnouncedAt = Date.now()
  pendingTimer = null
}

watch(
  announcement,
  (next) => {
    if (!props.live || next === '' || next === announced.value) return
    const elapsed = Date.now() - lastAnnouncedAt
    if (elapsed >= props.liveThrottleMs) {
      flushAnnouncement()
      return
    }
    // Within the window: defer to the trailing edge so the latest value wins,
    // collapsing any intermediate updates into a single announcement.
    if (pendingTimer === null) {
      pendingTimer = setTimeout(flushAnnouncement, props.liveThrottleMs - elapsed)
    }
  },
  { immediate: true },
)

onUnmounted(() => {
  if (pendingTimer !== null) clearTimeout(pendingTimer)
})
</script>

<template>
  <article
    class="kpi"
    :aria-label="`${label}: ${value}`"
    :aria-busy="loading || undefined"
  >
    <p class="kpi__label">{{ label }}</p>
    <p
      class="kpi__value"
      :class="{ 'kpi__value--mono': mono, 'kpi__value--pulse': pulsing }"
      :title="value"
      @animationend="onPulseEnd"
    >
      <span v-if="loading" class="kpi__placeholder" aria-hidden="true">—</span>
      <span v-else>{{ value }}</span>
    </p>
    <span v-if="live" class="sr-only" aria-live="polite" aria-atomic="true">
      {{ announced }}
    </span>
  </article>
</template>

<style scoped>
.kpi {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-1);
  min-width: 0;
  padding: var(--sf-space-4) var(--sf-space-5);
  background-color: var(--sf-bg-surface);
  border: 1px solid var(--sf-border);
  border-radius: var(--sf-radius-xl);
  box-shadow: var(--sf-shadow-sm);
}

.kpi__label {
  margin: 0;
  font-size: var(--sf-text-xs);
  font-weight: var(--sf-weight-medium);
  letter-spacing: var(--sf-tracking-wide);
  color: var(--sf-fg-secondary);
  text-transform: uppercase;
}

.kpi__value {
  margin: 0;
  font-size: var(--sf-text-2xl);
  font-weight: var(--sf-weight-semibold);
  line-height: var(--sf-leading-tight);
  letter-spacing: var(--sf-tracking-tight);
  color: var(--sf-fg-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.kpi__value--mono {
  font-family: var(--sf-font-mono);
  font-size: var(--sf-text-lg);
}

.kpi__placeholder {
  color: var(--sf-fg-muted);
}

.kpi__value--pulse {
  animation: kpi-pulse 600ms var(--sf-ease-out);
}

@keyframes kpi-pulse {
  0% {
    color: var(--sf-accent);
    text-shadow: 0 0 0 var(--sf-accent-subtle);
  }
  40% {
    color: var(--sf-accent);
    text-shadow: 0 0 16px var(--sf-accent);
  }
  100% {
    color: var(--sf-fg-primary);
    text-shadow: 0 0 0 transparent;
  }
}

@media (prefers-reduced-motion: reduce) {
  .kpi__value--pulse {
    animation: none;
  }
}
</style>
