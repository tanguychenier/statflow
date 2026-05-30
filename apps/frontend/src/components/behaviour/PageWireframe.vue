<script setup lang="ts">
// =============================================================================
// PageWireframe — neutral page mock as a heatmap backdrop (screens.md §2.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// When no real page screenshot is available, the overlay still needs a stable
// rendered surface at the device's aspect ratio so coordinates land predictably.
// This draws a low-contrast wireframe (header, hero, content blocks) inside the
// requested viewport ratio. Decorative only — hidden from assistive tech.
// =============================================================================
import { computed } from 'vue'
import type { DeviceKind } from './heatmapModel'

const props = withDefaults(defineProps<{ device?: DeviceKind }>(), { device: 'desktop' })

const aspectRatio = computed(() => {
  switch (props.device) {
    case 'mobile':
      return '9 / 16'
    case 'tablet':
      return '3 / 4'
    case 'desktop':
    default:
      return '16 / 10'
  }
})
</script>

<template>
  <div class="wireframe" :style="{ aspectRatio }" aria-hidden="true">
    <div class="wireframe__bar">
      <span class="wireframe__logo" />
      <span class="wireframe__nav" />
      <span class="wireframe__nav" />
      <span class="wireframe__nav" />
    </div>
    <div class="wireframe__hero">
      <span class="wireframe__line wireframe__line--lg" />
      <span class="wireframe__line wireframe__line--md" />
      <span class="wireframe__cta" />
    </div>
    <div class="wireframe__grid">
      <span class="wireframe__card" />
      <span class="wireframe__card" />
      <span class="wireframe__card" />
    </div>
    <div class="wireframe__rows">
      <span class="wireframe__line wireframe__line--full" />
      <span class="wireframe__line wireframe__line--full" />
      <span class="wireframe__line wireframe__line--sm" />
    </div>
  </div>
</template>

<style scoped>
.wireframe {
  display: flex;
  flex-direction: column;
  gap: 6%;
  width: 100%;
  padding: 4%;
  background: var(--sf-bg-subtle);
  border-radius: var(--sf-radius-lg);
}

.wireframe__bar {
  display: flex;
  align-items: center;
  gap: 4%;
  padding-bottom: 3%;
  border-bottom: 1px solid var(--sf-border);
}

.wireframe__logo,
.wireframe__nav,
.wireframe__line,
.wireframe__cta,
.wireframe__card {
  display: block;
  background: var(--sf-border);
  border-radius: var(--sf-radius-sm);
}

.wireframe__logo {
  width: 12%;
  height: 1.5rem;
}

.wireframe__nav {
  width: 8%;
  height: 0.75rem;
}

.wireframe__hero {
  display: flex;
  flex-direction: column;
  gap: 2%;
  align-items: center;
  padding: 4% 0;
}

.wireframe__line--lg {
  width: 60%;
  height: 1.75rem;
}

.wireframe__line--md {
  width: 40%;
  height: 1rem;
}

.wireframe__line--full {
  width: 100%;
  height: 0.75rem;
}

.wireframe__line--sm {
  width: 50%;
  height: 0.75rem;
}

.wireframe__cta {
  width: 18%;
  height: 2rem;
  margin-top: 2%;
  background: var(--sf-accent-subtle);
}

.wireframe__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4%;
}

.wireframe__card {
  height: 5rem;
}

.wireframe__rows {
  display: flex;
  flex-direction: column;
  gap: 3%;
}
</style>
