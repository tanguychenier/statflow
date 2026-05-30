<script setup lang="ts">
// =============================================================================
// HeatmapOverlay — pointer heatmap over a page screenshot (data-viz.md §3.6)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// A <canvas> is absolutely positioned over the screenshot <img> and painted
// from percentage-based click buckets (viewport-independent, from the API). The
// gradient blending is done in-component with a radial-blob accumulation pass
// then a colour-ramp lookup — no external runtime dependency. Intensity/radius
// changes only repaint the canvas (no new API calls), as the spec requires.
// =============================================================================
import { ref, watch, onMounted, nextTick } from 'vue'
import type { HeatmapClickPoint } from '@/api/types'

const props = withDefaults(
  defineProps<{
    screenshotUrl: string
    points: HeatmapClickPoint[]
    /** 0–1 multiplier on blob opacity. */
    intensity?: number
    /** Blob radius in px at the rendered width. */
    radius?: number
  }>(),
  { intensity: 0.6, radius: 30 },
)

const container = ref<HTMLElement | null>(null)
const canvas = ref<HTMLCanvasElement | null>(null)
const image = ref<HTMLImageElement | null>(null)

// Indigo → amber → rose ramp for low → high density.
const GRADIENT: Array<[number, [number, number, number]]> = [
  [0.0, [99, 102, 241]],
  [0.5, [245, 158, 11]],
  [1.0, [244, 63, 94]],
]

function rampColor(t: number): [number, number, number] {
  for (let i = 1; i < GRADIENT.length; i += 1) {
    const [stop, color] = GRADIENT[i]
    if (t <= stop) {
      const [prevStop, prevColor] = GRADIENT[i - 1]
      const f = (t - prevStop) / (stop - prevStop || 1)
      return [
        Math.round(prevColor[0] + (color[0] - prevColor[0]) * f),
        Math.round(prevColor[1] + (color[1] - prevColor[1]) * f),
        Math.round(prevColor[2] + (color[2] - prevColor[2]) * f),
      ]
    }
  }
  return GRADIENT[GRADIENT.length - 1][1]
}

function redraw() {
  const cvs = canvas.value
  const img = image.value
  if (!cvs || !img || !img.complete || img.naturalWidth === 0) return

  const width = img.clientWidth
  const height = img.clientHeight
  cvs.width = width
  cvs.height = height

  const ctx = cvs.getContext('2d')
  if (!ctx) return
  ctx.clearRect(0, 0, width, height)

  // Accumulate weighted radial blobs onto an alpha buffer.
  for (const point of props.points) {
    const x = (point.x_pct / 100) * width
    const y = (point.y_pct / 100) * height
    const gradient = ctx.createRadialGradient(x, y, 0, x, y, props.radius)
    const alpha = Math.min(1, point.weight * props.intensity)
    gradient.addColorStop(0, `rgba(0,0,0,${alpha})`)
    gradient.addColorStop(1, 'rgba(0,0,0,0)')
    ctx.fillStyle = gradient
    ctx.fillRect(x - props.radius, y - props.radius, props.radius * 2, props.radius * 2)
  }

  // Colourise: map accumulated alpha to the density ramp.
  const buffer = ctx.getImageData(0, 0, width, height)
  const data = buffer.data
  for (let i = 0; i < data.length; i += 4) {
    const a = data[i + 3]
    if (a === 0) continue
    const [r, g, b] = rampColor(a / 255)
    data[i] = r
    data[i + 1] = g
    data[i + 2] = b
  }
  ctx.putImageData(buffer, 0, 0)
}

onMounted(async () => {
  await nextTick()
  redraw()
})

watch(
  () => [props.points, props.intensity, props.radius],
  () => redraw(),
  { deep: true },
)

function onImageLoad() {
  redraw()
}

defineExpose({ redraw })
</script>

<template>
  <div ref="container" class="sf-heatmap relative inline-block w-full">
    <img
      ref="image"
      :src="screenshotUrl"
      alt=""
      class="block w-full select-none"
      @load="onImageLoad"
    />
    <canvas
      ref="canvas"
      class="pointer-events-none absolute inset-0 size-full mix-blend-multiply"
      aria-hidden="true"
    />
  </div>
</template>
