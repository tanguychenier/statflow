// =============================================================================
// Statflow Dashboard — ECharts themes
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Token-derived ECharts themes (data-viz.md §2). Values mirror the semantic
// tokens in tokens.css for each theme. Charts read `backgroundColor:
// 'transparent'` so the card surface shows through and theme switching is a
// pure re-init (handled by useChart).
// =============================================================================

const CHART_PALETTE = [
  '#6366f1', // chart-1 indigo
  '#8b5cf6', // chart-2 violet
  '#0ea5e9', // chart-3 sky
  '#10b981', // chart-4 emerald
  '#f59e0b', // chart-5 amber
  '#f43f5e', // chart-6 rose
  '#a78bfa', // chart-7 lavender
  '#34d399', // chart-8 mint
]

const FONT_FAMILY = 'Inter, "Inter Variable", ui-sans-serif, system-ui, sans-serif'

export const statflowDark = {
  color: CHART_PALETTE,
  backgroundColor: 'transparent',
  textStyle: { fontFamily: FONT_FAMILY, color: '#a0a0ab', fontSize: 12 },
  title: { textStyle: { color: '#fafafa', fontSize: 14, fontWeight: 600 } },
  legend: { textStyle: { color: '#a0a0ab' } },
  tooltip: {
    backgroundColor: '#18181b',
    borderColor: '#3f3f46',
    borderWidth: 1,
    textStyle: { color: '#fafafa' },
    extraCssText: 'box-shadow: 0 8px 24px rgba(0,0,0,0.60); border-radius: 8px;',
  },
  categoryAxis: {
    axisLine: { lineStyle: { color: '#27272a' } },
    axisTick: { lineStyle: { color: '#27272a' } },
    axisLabel: { color: '#71717a' },
    splitLine: { lineStyle: { color: '#27272a', type: 'dashed' } },
  },
  valueAxis: {
    axisLine: { lineStyle: { color: '#27272a' } },
    axisTick: { lineStyle: { color: '#27272a' } },
    axisLabel: { color: '#71717a' },
    splitLine: { lineStyle: { color: '#27272a', type: 'dashed' } },
  },
}

export const statflowLight = {
  color: CHART_PALETTE,
  backgroundColor: 'transparent',
  textStyle: { fontFamily: FONT_FAMILY, color: '#52525b', fontSize: 12 },
  title: { textStyle: { color: '#18181b', fontSize: 14, fontWeight: 600 } },
  legend: { textStyle: { color: '#52525b' } },
  tooltip: {
    backgroundColor: '#ffffff',
    borderColor: '#d1d1d6',
    borderWidth: 1,
    textStyle: { color: '#18181b' },
    extraCssText: 'box-shadow: 0 8px 24px rgba(0,0,0,0.10); border-radius: 8px;',
  },
  categoryAxis: {
    axisLine: { lineStyle: { color: '#e4e4e7' } },
    axisTick: { lineStyle: { color: '#e4e4e7' } },
    axisLabel: { color: '#a0a0ab' },
    splitLine: { lineStyle: { color: '#e4e4e7', type: 'dashed' } },
  },
  valueAxis: {
    axisLine: { lineStyle: { color: '#e4e4e7' } },
    axisTick: { lineStyle: { color: '#e4e4e7' } },
    axisLabel: { color: '#a0a0ab' },
    splitLine: { lineStyle: { color: '#e4e4e7', type: 'dashed' } },
  },
}

export const CHART_COLORS = CHART_PALETTE
export const THEME_DARK = 'statflow-dark'
export const THEME_LIGHT = 'statflow-light'
