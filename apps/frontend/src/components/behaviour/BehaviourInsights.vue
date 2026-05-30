<script setup lang="ts">
// =============================================================================
// BehaviourInsights — rage-click & dead-click callouts (screens.md §2)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// A frustration summary that surfaces the selectors users rage- or dead-click
// most, independent of the active overlay so the warning is always in view.
// Receives shaped insight rows (heatmapModel.buildInsights).
// =============================================================================
import { useI18n } from 'vue-i18n'
import { MousePointerClick, Ban } from 'lucide-vue-next'
import Card from '@/components/ui/card/Card.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import { Skeleton } from '@/components/ui/skeleton'
import type { BehaviourInsight } from './heatmapModel'

withDefaults(
  defineProps<{ rage: BehaviourInsight[]; dead: BehaviourInsight[]; loading?: boolean }>(),
  { loading: false },
)

const { n } = useI18n()
</script>

<template>
  <Card class="insights">
    <div class="insights__group">
      <header class="insights__head">
        <MousePointerClick class="insights__icon insights__icon--rage" aria-hidden="true" />
        <h3 class="insights__title">{{ $t('heatmaps.insights.rageTitle') }}</h3>
        <Badge variant="warning">{{ $t('heatmaps.insights.rageBadge') }}</Badge>
      </header>
      <p class="insights__hint">{{ $t('heatmaps.insights.rageHint') }}</p>
      <ul v-if="loading" class="insights__list" aria-busy="true">
        <li v-for="i in 3" :key="i"><Skeleton class="h-4 w-full" /></li>
      </ul>
      <ul v-else-if="rage.length > 0" class="insights__list">
        <li v-for="item in rage" :key="item.selector" class="insights__row">
          <span class="insights__selector" :title="item.selector">{{ item.selector }}</span>
          <span class="insights__count">{{ n(item.count, 'compact') }}</span>
        </li>
      </ul>
      <p v-else class="insights__empty">{{ $t('heatmaps.insights.none') }}</p>
    </div>

    <div class="insights__group">
      <header class="insights__head">
        <Ban class="insights__icon insights__icon--dead" aria-hidden="true" />
        <h3 class="insights__title">{{ $t('heatmaps.insights.deadTitle') }}</h3>
        <Badge variant="error">{{ $t('heatmaps.insights.deadBadge') }}</Badge>
      </header>
      <p class="insights__hint">{{ $t('heatmaps.insights.deadHint') }}</p>
      <ul v-if="loading" class="insights__list" aria-busy="true">
        <li v-for="i in 3" :key="i"><Skeleton class="h-4 w-full" /></li>
      </ul>
      <ul v-else-if="dead.length > 0" class="insights__list">
        <li v-for="item in dead" :key="item.selector" class="insights__row">
          <span class="insights__selector" :title="item.selector">{{ item.selector }}</span>
          <span class="insights__count">{{ n(item.count, 'compact') }}</span>
        </li>
      </ul>
      <p v-else class="insights__empty">{{ $t('heatmaps.insights.none') }}</p>
    </div>
  </Card>
</template>

<style scoped>
.insights {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--sf-space-6);
}

.insights__group {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-2);
}

.insights__head {
  display: flex;
  align-items: center;
  gap: var(--sf-space-2);
}

.insights__icon {
  width: 1rem;
  height: 1rem;
}

.insights__icon--rage {
  color: var(--sf-warning-text);
}

.insights__icon--dead {
  color: var(--sf-negative-text);
}

.insights__title {
  margin: 0;
  font-size: var(--sf-text-sm);
  font-weight: var(--sf-weight-semibold);
  color: var(--sf-fg-primary);
}

.insights__hint {
  margin: 0;
  font-size: var(--sf-text-xs);
  color: var(--sf-fg-muted);
}

.insights__list {
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-2);
  margin: var(--sf-space-1) 0 0;
  padding: 0;
  list-style: none;
}

.insights__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--sf-space-3);
}

.insights__selector {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-family: var(--sf-font-mono);
  font-size: var(--sf-text-xs);
  color: var(--sf-fg-secondary);
}

.insights__count {
  font-size: var(--sf-text-sm);
  font-variant-numeric: tabular-nums;
  color: var(--sf-fg-primary);
}

.insights__empty {
  margin: var(--sf-space-1) 0 0;
  font-size: var(--sf-text-sm);
  color: var(--sf-fg-muted);
}

@media (width < 768px) {
  .insights {
    grid-template-columns: 1fr;
  }
}
</style>
