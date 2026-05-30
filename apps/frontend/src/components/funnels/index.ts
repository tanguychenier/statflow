// =============================================================================
// Funnels — screen component barrel (screens.md §3)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

export { default as FunnelListItem } from './FunnelListItem.vue'
export { default as FunnelSummaryStats } from './FunnelSummaryStats.vue'
export { default as FunnelDetail } from './FunnelDetail.vue'
export { default as FunnelBuilderModal } from './FunnelBuilderModal.vue'
export { default as FunnelStepRow } from './FunnelStepRow.vue'
export { default as FunnelBreakdownPanel } from './FunnelBreakdownPanel.vue'

export * from './funnelStats'
export * from './funnelBuilder'
export * from './funnelBreakdown'
export * from './funnelGoalFilter'
export { useUpdateFunnel } from './useUpdateFunnel'
