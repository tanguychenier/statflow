<script setup lang="ts">
// =============================================================================
// ScrollDepthOverlay — vertical reach gradient over a page (data-viz.md §3.6)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The scroll heatmap is not a click blob field: it is a top-to-bottom gradient
// where the colour at depth d encodes the share of sessions that scrolled at
// least that far, plus a dashed "fold" line at the median reach. Rendered with
// pure CSS gradients so it scales crisply and needs no canvas.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ScrollDepthBar } from './heatmapModel'

const props = defineProps<{ bars: ScrollDepthBar[]; foldDepthPct: number | null }>()

const { n } = useI18n()

/**
 * Build a vertical linear-gradient where each documented depth contributes a
 * colour stop interpolated from warm (high reach) to cool (low reach). The bars
 * arrive deepest-first, so reverse to paint top (0%) → bottom (100%).
 */
const gradient = computed(() => {
  if (props.bars.length === 0) return 'transparent'
  const ascending = [...props.bars].sort((a, b) => a.depthPct - b.depthPct)
  const stops = ascending.map((bar) => {
    const t = bar.sessionsPct / 100
    const r = Math.round(244 - (244 - 99) * (1 - t))
    const g = Math.round(63 + (158 - 63) * (1 - t))
    const b = Math.round(94 + (241 - 94) * (1 - t))
    return `rgba(${r}, ${g}, ${b}, 0.55) ${bar.depthPct}%`
  })
  return `linear-gradient(to bottom, ${stops.join(', ')})`
})
</script>

<template>
  <div class="scroll-overlay" aria-hidden="true">
    <div class="scroll-overlay__fill" :style="{ background: gradient }" />
    <div
      v-if="foldDepthPct !== null"
      class="scroll-overlay__fold"
      :style="{ top: `${foldDepthPct}%` }"
    >
      <span class="scroll-overlay__fold-label">
        {{ $t('heatmaps.scroll.fold', { pct: n(foldDepthPct / 100, 'percent') }) }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.scroll-overlay {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.scroll-overlay__fill {
  position: absolute;
  inset: 0;
}

.scroll-overlay__fold {
  position: absolute;
  left: 0;
  right: 0;
  border-top: 2px dashed var(--sf-fg-primary);
}

.scroll-overlay__fold-label {
  position: absolute;
  top: 0.25rem;
  right: 0.5rem;
  padding: 0.125rem 0.5rem;
  border-radius: var(--sf-radius-full);
  background: var(--sf-bg-overlay);
  color: var(--sf-fg-primary);
  font-size: var(--sf-text-xs);
  font-weight: var(--sf-weight-semibold);
}
</style>
