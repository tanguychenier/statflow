<script setup lang="ts">
// =============================================================================
// MetricCard — KPI card with value, trend badge, and sparkline placeholder
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Design spec: components.md § 4.2
// Token usage:  semantic tokens only — no primitives
// Accessibility: WCAG 2.2 AA — trend text readable, loading states announced
// =============================================================================
import { computed } from 'vue'
import type { Trend } from '@/types'
import SparkLine from '@/components/charts/SparkLine.vue'

export interface MetricCardProps {
  /** Visible card title */
  title: string
  /** Formatted display value (e.g. "84,203" or "2m 04s") */
  value: string
  /** Trend object — direction and magnitude */
  trend?: Trend
  /** Compact daily series for the embedded sparkline (data-viz.md §3.8) */
  sparkData?: number[]
  /** Whether the card is in a loading skeleton state */
  loading?: boolean
  /** Accessible description used in the aria-label for screen readers */
  description?: string
}

const props = withDefaults(defineProps<MetricCardProps>(), {
  loading: false,
  trend: undefined,
  sparkData: undefined,
  description: undefined,
})

const hasSpark = computed(() => Array.isArray(props.sparkData) && props.sparkData.length > 0)
const sparkValues = computed<number[]>(() => props.sparkData ?? [])

/** Whether an upward movement is semantically positive for this metric */
const trendIsPositive = computed(() => {
  if (!props.trend) return null
  if (props.trend.direction === 'neutral') return null
  const goingUp = props.trend.direction === 'up'
  return props.trend.positiveIsUp ? goingUp : !goingUp
})

const trendLabel = computed(() => {
  if (!props.trend) return ''
  const sign = props.trend.direction === 'up' ? '+' : props.trend.direction === 'down' ? '-' : ''
  return `${sign}${Math.abs(props.trend.value).toFixed(1)}%`
})

const trendAriaLabel = computed(() => {
  if (!props.trend) return ''
  const dir =
    props.trend.direction === 'up' ? 'Up' : props.trend.direction === 'down' ? 'Down' : 'No change'
  return `${dir} ${Math.abs(props.trend.value).toFixed(1)}%`
})

const cardAriaLabel = computed(() => {
  if (props.description) return props.description
  if (props.trend) {
    return `${props.title}: ${props.value}. ${trendAriaLabel.value}`
  }
  return `${props.title}: ${props.value}`
})
</script>

<template>
  <article
    class="metric-card"
    :class="{ 'metric-card--loading': loading }"
    :aria-label="cardAriaLabel"
    :aria-busy="loading"
  >
    <!-- Loading skeleton state -->
    <template v-if="loading">
      <div class="metric-card__skeleton metric-card__skeleton--title" aria-hidden="true" />
      <div class="metric-card__skeleton metric-card__skeleton--value" aria-hidden="true" />
      <div class="metric-card__skeleton metric-card__skeleton--trend" aria-hidden="true" />
      <div class="metric-card__skeleton metric-card__skeleton--spark" aria-hidden="true" />
      <span class="sr-only">{{ $t('common.loading') }}</span>
    </template>

    <!-- Loaded state -->
    <template v-else>
      <p class="metric-card__title">{{ title }}</p>

      <p class="metric-card__value">{{ value }}</p>

      <div v-if="trend" class="metric-card__trend">
        <span
          class="metric-card__trend-badge"
          :class="{
            'metric-card__trend-badge--positive': trendIsPositive === true,
            'metric-card__trend-badge--negative': trendIsPositive === false,
            'metric-card__trend-badge--neutral': trendIsPositive === null,
          }"
          :aria-label="trendAriaLabel"
        >
          <span class="metric-card__trend-arrow" aria-hidden="true">
            {{ trend.direction === 'up' ? '▲' : trend.direction === 'down' ? '▼' : '—' }}
          </span>
          <span>{{ trendLabel }}</span>
        </span>
      </div>

      <!-- Embedded micro sparkline; falls back to a static block when no data.
          Decorative: the value and trend already carry the data, and the card's
          aria-label voices both, so the sparkline stays out of the a11y tree. -->
      <div class="metric-card__sparkline" aria-hidden="true">
        <SparkLine
          v-if="hasSpark"
          :data="sparkValues"
          :direction="trend?.direction ?? 'neutral'"
        />
      </div>
    </template>
  </article>
</template>

<style scoped>
.metric-card {
  background-color: var(--sf-bg-surface);
  border: 1px solid var(--sf-border);
  border-radius: var(--sf-radius-xl);
  padding: var(--sf-space-5) var(--sf-space-6);
  box-shadow: var(--sf-shadow-sm);
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-2);
  min-width: 0;
}

.metric-card__title {
  margin: 0;
  font-size: var(--sf-text-sm);
  font-weight: var(--sf-weight-medium);
  color: var(--sf-fg-secondary);
  letter-spacing: var(--sf-tracking-wide);
}

.metric-card__value {
  margin: 0;
  font-size: var(--sf-text-3xl);
  font-weight: var(--sf-weight-semibold);
  color: var(--sf-fg-primary);
  letter-spacing: var(--sf-tracking-tight);
  line-height: var(--sf-leading-tight);
  font-family: var(--sf-font-sans);
}

/* Trend badge */
.metric-card__trend {
  display: flex;
  align-items: center;
  gap: var(--sf-space-2);
}

.metric-card__trend-badge {
  display: inline-flex;
  align-items: center;
  gap: var(--sf-space-1);
  padding: var(--sf-space-0-5) var(--sf-space-2);
  border-radius: var(--sf-radius-full);
  font-size: var(--sf-text-xs);
  font-weight: var(--sf-weight-semibold);
}

.metric-card__trend-badge--positive {
  background-color: var(--sf-positive-bg);
  color: var(--sf-positive-text);
}

.metric-card__trend-badge--negative {
  background-color: var(--sf-negative-bg);
  color: var(--sf-negative-text);
}

.metric-card__trend-badge--neutral {
  background-color: var(--sf-bg-subtle);
  color: var(--sf-fg-secondary);
}

.metric-card__trend-arrow {
  font-size: 0.6rem;
}

/* Sparkline placeholder */
.metric-card__sparkline {
  height: 64px;
  border-radius: var(--sf-radius-sm);
  background-color: var(--sf-bg-subtle);
  margin-block-start: var(--sf-space-2);
  /* Filled by ECharts in Phase 2 */
}

/* Skeleton shimmer */
.metric-card--loading {
  pointer-events: none;
}

.metric-card__skeleton {
  border-radius: var(--sf-radius-sm);
  background: linear-gradient(
    90deg,
    var(--sf-bg-subtle) 25%,
    var(--sf-bg-muted) 50%,
    var(--sf-bg-subtle) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s var(--sf-ease-linear) infinite;
}

.metric-card__skeleton--title {
  height: 14px;
  width: 60%;
}

.metric-card__skeleton--value {
  height: 36px;
  width: 80%;
}

.metric-card__skeleton--trend {
  height: 20px;
  width: 40%;
}

.metric-card__skeleton--spark {
  height: 64px;
  width: 100%;
  margin-block-start: var(--sf-space-2);
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .metric-card__skeleton {
    animation: none;
    background: var(--sf-bg-subtle);
  }
}
</style>
