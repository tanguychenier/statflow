// =============================================================================
// Statflow Dashboard — useChart composable
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Owns an ECharts instance's full lifecycle for a target element (data-viz.md
// §1.2): init with the active Statflow theme, reactive option updates, resize
// via ResizeObserver, theme re-init on dark/light switch, and disposal. Charts
// never listen to window resize directly — the observer handles container size.
// =============================================================================

import { onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue'
import { storeToRefs } from 'pinia'
import { echarts, type ECharts, type EChartsOption } from '@/charts/echarts'
import { THEME_DARK, THEME_LIGHT } from '@/charts/themes'
import { useThemeStore } from '@/stores/theme'

export function useChart(el: Ref<HTMLElement | null>, option: Ref<EChartsOption>) {
  const chart = ref<ECharts | null>(null)
  const themeStore = useThemeStore()
  const { isDark } = storeToRefs(themeStore)
  let observer: ResizeObserver | null = null

  function themeName(): string {
    return isDark.value ? THEME_DARK : THEME_LIGHT
  }

  function instantiate() {
    if (!el.value || chart.value) return
    chart.value = echarts.init(el.value, themeName(), { renderer: 'canvas' })
    chart.value.setOption(option.value)
    if (typeof ResizeObserver !== 'undefined') {
      observer = new ResizeObserver(() => chart.value?.resize())
      observer.observe(el.value)
    }
  }

  function teardown() {
    observer?.disconnect()
    observer = null
    chart.value?.dispose()
    chart.value = null
  }

  onMounted(instantiate)

  // The target element may appear after mount (e.g. once a loading state in the
  // ChartWrapper clears), so (re)instantiate whenever the ref resolves/changes.
  watch(el, (next) => {
    if (next) instantiate()
    else teardown()
  })

  // Apply data/option changes in place (cheap, preserves animation state).
  watch(
    option,
    (next) => chart.value?.setOption(next, { notMerge: false, lazyUpdate: true }),
    { deep: true },
  )

  // Theme is baked into the instance at init, so a switch requires a re-init.
  watch(isDark, () => {
    if (!el.value || !chart.value) return
    const current = option.value
    teardown()
    instantiate()
    chart.value?.setOption(current)
  })

  onBeforeUnmount(teardown)

  return { chart }
}
