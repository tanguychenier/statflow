// =============================================================================
// Statflow Dashboard — ECharts core registration
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Tree-shaken ECharts setup (data-viz.md §1.2). Only the chart types and
// components Statflow actually renders are registered, keeping the bundle lean.
// The Statflow themes are registered once on import so every chart instance can
// reference them by name.
// =============================================================================

import * as echarts from 'echarts/core'
import {
  BarChart,
  FunnelChart,
  HeatmapChart,
  LineChart,
  MapChart,
  PieChart,
  ScatterChart,
} from 'echarts/charts'
import {
  DataZoomComponent,
  GeoComponent,
  GridComponent,
  LegendComponent,
  MarkLineComponent,
  TitleComponent,
  TooltipComponent,
  VisualMapComponent,
} from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import { statflowDark, statflowLight, THEME_DARK, THEME_LIGHT } from './themes'

echarts.use([
  LineChart,
  BarChart,
  FunnelChart,
  HeatmapChart,
  MapChart,
  PieChart,
  ScatterChart,
  CanvasRenderer,
  GridComponent,
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  DataZoomComponent,
  VisualMapComponent,
  GeoComponent,
  MarkLineComponent,
])

echarts.registerTheme(THEME_DARK, statflowDark)
echarts.registerTheme(THEME_LIGHT, statflowLight)

export { echarts }
export type { EChartsOption } from 'echarts'
export type ECharts = echarts.ECharts
