<script setup lang="ts">
// =============================================================================
// BehaviourStatsPanel — totals, top elements & scroll depth (screens.md §2.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The right-hand panel of the Heatmaps screen. Pure presentation: it receives
// already-shaped view-models (see heatmapModel.ts) and renders three blocks —
// headline counts, the ranked top-elements list, and the scroll-depth bars.
// =============================================================================
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/card/Card.vue'
import Divider from '@/components/ui/divider/Divider.vue'
import { Skeleton } from '@/components/ui/skeleton'
import ProgressBar from '@/components/charts/ProgressBar.vue'
import type { ScrollDepthBar, TopElement } from './heatmapModel'

withDefaults(
  defineProps<{
    totalEvents: number
    uniqueUsers: number | null
    sampleSize: number
    topElements: TopElement[]
    scrollDepth: ScrollDepthBar[]
    loading?: boolean
  }>(),
  { loading: false },
)

const { n } = useI18n()
</script>

<template>
  <Card class="behaviour-stats" aria-live="polite">
    <h2 class="behaviour-stats__heading">{{ $t('heatmaps.panel.title') }}</h2>

    <dl class="behaviour-stats__metrics">
      <div class="behaviour-stats__metric">
        <dt>{{ $t('heatmaps.stats.totalClicks') }}</dt>
        <dd v-if="!loading">{{ n(totalEvents, 'compact') }}</dd>
        <dd v-else><Skeleton class="h-5 w-16" /></dd>
      </div>
      <div class="behaviour-stats__metric">
        <dt>{{ $t('heatmaps.stats.uniqueUsers') }}</dt>
        <dd v-if="!loading">{{ uniqueUsers === null ? '—' : n(uniqueUsers, 'compact') }}</dd>
        <dd v-else><Skeleton class="h-5 w-16" /></dd>
      </div>
      <div class="behaviour-stats__metric">
        <dt>{{ $t('heatmaps.stats.sampleSize') }}</dt>
        <dd v-if="!loading">{{ n(sampleSize, 'compact') }}</dd>
        <dd v-else><Skeleton class="h-5 w-16" /></dd>
      </div>
    </dl>

    <Divider />

    <section class="behaviour-stats__section" :aria-label="$t('heatmaps.topElements')">
      <h3 class="behaviour-stats__subheading">{{ $t('heatmaps.topElements') }}</h3>
      <ul v-if="topElements.length > 0" class="behaviour-stats__list">
        <li v-for="element in topElements" :key="element.selector" class="behaviour-stats__row">
          <span class="behaviour-stats__row-head">
            <span class="behaviour-stats__selector" :title="element.selector">
              {{ element.selector }}
            </span>
            <span class="behaviour-stats__share">{{ n(element.sharePct / 100, 'percent') }}</span>
          </span>
          <ProgressBar :value="element.sharePct" :label="element.selector" />
        </li>
      </ul>
      <p v-else class="behaviour-stats__empty">{{ $t('common.noData') }}</p>
    </section>

    <Divider />

    <section class="behaviour-stats__section" :aria-label="$t('heatmaps.scrollDepth')">
      <h3 class="behaviour-stats__subheading">{{ $t('heatmaps.scrollDepth') }}</h3>
      <ul v-if="scrollDepth.length > 0" class="behaviour-stats__list">
        <li v-for="bar in scrollDepth" :key="bar.depthPct" class="behaviour-stats__row">
          <span class="behaviour-stats__row-head">
            <span>{{ bar.depthPct }}%</span>
            <span class="behaviour-stats__share">{{ n(bar.sessionsPct / 100, 'percent') }}</span>
          </span>
          <ProgressBar :value="bar.sessionsPct" :label="`${bar.depthPct}%`" />
        </li>
      </ul>
      <p v-else class="behaviour-stats__empty">{{ $t('common.noData') }}</p>
    </section>
  </Card>
</template>

<style scoped>
.behaviour-stats {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-4);
}

.behaviour-stats__heading {
  margin: 0;
  font-size: var(--sf-text-sm);
  font-weight: var(--sf-weight-semibold);
  color: var(--sf-fg-primary);
}

.behaviour-stats__metrics {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-2);
  margin: 0;
}

.behaviour-stats__metric {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--sf-space-3);
}

.behaviour-stats__metric dt {
  font-size: var(--sf-text-sm);
  color: var(--sf-fg-secondary);
}

.behaviour-stats__metric dd {
  margin: 0;
  font-size: var(--sf-text-sm);
  font-weight: var(--sf-weight-semibold);
  font-variant-numeric: tabular-nums;
  color: var(--sf-fg-primary);
}

.behaviour-stats__section {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-3);
}

.behaviour-stats__subheading {
  margin: 0;
  font-size: var(--sf-text-xs);
  font-weight: var(--sf-weight-semibold);
  text-transform: uppercase;
  letter-spacing: var(--sf-tracking-wide);
  color: var(--sf-fg-muted);
}

.behaviour-stats__list {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-3);
  margin: 0;
  padding: 0;
  list-style: none;
}

.behaviour-stats__row {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-1);
}

.behaviour-stats__row-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--sf-space-2);
  font-size: var(--sf-text-sm);
  color: var(--sf-fg-secondary);
}

.behaviour-stats__selector {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-family: var(--sf-font-mono);
  font-size: var(--sf-text-xs);
  color: var(--sf-fg-primary);
}

.behaviour-stats__share {
  font-variant-numeric: tabular-nums;
  color: var(--sf-fg-primary);
}

.behaviour-stats__empty {
  margin: 0;
  font-size: var(--sf-text-sm);
  color: var(--sf-fg-muted);
}
</style>
