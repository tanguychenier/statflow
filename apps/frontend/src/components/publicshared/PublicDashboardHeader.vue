<script setup lang="ts">
// =============================================================================
// PublicDashboardHeader — minimal header for the shared dashboard (screens.md §8.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Logo, optional site label, display-only period, and a "Powered by Statflow"
// attribution badge that opens the marketing site in a new tab. No navigation,
// no picker — the period is whatever the owner configured and is shown read-only.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ExternalLink } from 'lucide-vue-next'
import type { PublicPeriod } from './publicPeriod'

const props = withDefaults(
  defineProps<{
    /** Owner-configured reporting period; null hides the period line. */
    period?: PublicPeriod | null
    /** Optional human label for the site (kept for forward-compatibility). */
    siteLabel?: string | null
    /** Skeleton state while the first fetch is in flight. */
    loading?: boolean
  }>(),
  { period: null, siteLabel: null, loading: false },
)

const { t, d } = useI18n()

const POWERED_BY_URL = 'https://statflow.io'

const periodLabel = computed(() => {
  if (!props.period) return ''
  return `${d(props.period.from, 'dateOnly')} – ${d(props.period.to, 'dateOnly')}`
})

const heading = computed(() =>
  props.siteLabel
    ? t('publicDashboard.header.titleFor', { site: props.siteLabel })
    : t('publicDashboard.header.title'),
)
</script>

<template>
  <header class="public-header">
    <div class="public-header__bar">
      <div class="public-header__brand" aria-hidden="true">
        <span class="public-header__brand-mark">◆</span> Statflow
      </div>

      <a
        class="public-header__powered"
        :href="POWERED_BY_URL"
        target="_blank"
        rel="noopener noreferrer"
        :aria-label="t('publicDashboard.header.poweredBy')"
      >
        {{ t('publicDashboard.header.poweredByShort') }}
        <ExternalLink class="size-3.5" aria-hidden="true" />
      </a>
    </div>

    <div class="public-header__meta">
      <h1 class="public-header__title">{{ heading }}</h1>
      <p v-if="loading" class="public-header__period public-header__period--loading" aria-hidden="true" />
      <p v-else-if="periodLabel" class="public-header__period">{{ periodLabel }}</p>
    </div>
  </header>
</template>

<style scoped>
.public-header {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-4);
}

.public-header__bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sf-space-4);
  flex-wrap: wrap;
}

.public-header__brand {
  font-size: var(--sf-text-lg);
  font-weight: var(--sf-weight-semibold);
  color: var(--sf-fg-primary);
}

.public-header__brand-mark {
  color: var(--sf-accent);
}

.public-header__powered {
  display: inline-flex;
  align-items: center;
  gap: var(--sf-space-1-5);
  padding: var(--sf-space-1) var(--sf-space-3);
  border: 1px solid var(--sf-border);
  border-radius: var(--sf-radius-full);
  font-size: var(--sf-text-xs);
  font-weight: var(--sf-weight-medium);
  color: var(--sf-fg-secondary);
  background-color: var(--sf-bg-surface);
  text-decoration: none;
  transition: color 0.15s ease, border-color 0.15s ease;
  outline: none;
}

.public-header__powered:hover {
  color: var(--sf-fg-primary);
  border-color: var(--sf-border-strong);
}

.public-header__powered:focus-visible {
  box-shadow: 0 0 0 2px var(--sf-border-focus);
}

.public-header__meta {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-1);
}

.public-header__title {
  margin: 0;
  font-size: var(--sf-text-2xl);
  font-weight: var(--sf-weight-semibold);
  letter-spacing: var(--sf-tracking-tight);
  color: var(--sf-fg-primary);
}

.public-header__period {
  margin: 0;
  font-size: var(--sf-text-sm);
  color: var(--sf-fg-secondary);
}

.public-header__period--loading {
  height: 1rem;
  width: 12rem;
  border-radius: var(--sf-radius-sm);
  background-color: var(--sf-bg-subtle);
}
</style>
