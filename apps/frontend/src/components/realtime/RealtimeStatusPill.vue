<script setup lang="ts">
// =============================================================================
// RealtimeStatusPill — connection + freshness indicator (screens.md §4.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// "● Live — updated 2s ago" header chip. The dot blinks while the stream is
// open (suppressed under reduced motion) and turns amber while reconnecting /
// red on a hard error, giving an at-a-glance signal of stream health. The
// "updated Ns ago" label ticks every second off the last `stats` arrival.
// =============================================================================
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useReducedMotion } from '@/composables/useReducedMotion'
import type { RealtimeStreamStatus } from './useRealtimeStream'

const props = defineProps<{
  status: RealtimeStreamStatus
  /** Epoch ms of the last received stats payload, or null before the first. */
  lastStatsAt: number | null
}>()

const { t } = useI18n()
const { prefersReduced } = useReducedMotion()

// Drive the relative label off a ticking clock so it refreshes without new data.
const nowTick = ref(Date.now())
let timer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  timer = setInterval(() => {
    nowTick.value = Date.now()
  }, 1_000)
})

onUnmounted(() => {
  if (timer !== null) clearInterval(timer)
})

const isLive = computed(() => props.status === 'open')
const tone = computed(() => {
  if (props.status === 'open') return 'live'
  if (props.status === 'error') return 'error'
  return 'pending'
})

const secondsAgo = computed(() => {
  if (props.lastStatsAt === null) return null
  return Math.max(0, Math.round((nowTick.value - props.lastStatsAt) / 1_000))
})

const label = computed(() => {
  if (props.status === 'error') return t('realtime.status.reconnecting')
  if (props.status === 'connecting' || props.status === 'reconnecting' || secondsAgo.value === null) {
    return t('realtime.status.connecting')
  }
  return t('realtime.updatedAgo', { time: t('realtime.secondsAgo', { count: secondsAgo.value }, secondsAgo.value) })
})
</script>

<template>
  <p class="status" :class="`status--${tone}`" role="status" aria-live="polite">
    <span
      class="status__dot"
      :class="{ 'status__dot--blink': isLive && !prefersReduced }"
      aria-hidden="true"
    />
    <span class="status__text">{{ t('realtime.live') }} — {{ label }}</span>
  </p>
</template>

<style scoped>
.status {
  display: inline-flex;
  align-items: center;
  gap: var(--sf-space-2);
  margin: 0;
  font-size: var(--sf-text-sm);
  color: var(--sf-fg-secondary);
}

.status__dot {
  width: 8px;
  height: 8px;
  border-radius: var(--sf-radius-full);
  flex-shrink: 0;
  background-color: var(--sf-fg-muted);
}

.status--live .status__dot {
  background-color: var(--sf-positive);
}

.status--error .status__dot {
  background-color: var(--sf-negative);
}

.status--pending .status__dot {
  background-color: var(--sf-warning);
}

.status__dot--blink {
  animation: status-blink 1.6s var(--sf-ease-in-out) infinite;
}

@keyframes status-blink {
  0%,
  100% {
    opacity: 1;
    box-shadow: 0 0 0 0 var(--sf-positive-bg);
  }
  50% {
    opacity: 0.6;
    box-shadow: 0 0 0 4px transparent;
  }
}

@media (prefers-reduced-motion: reduce) {
  .status__dot--blink {
    animation: none;
  }
}
</style>
