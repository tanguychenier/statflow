// =============================================================================
// Statflow Dashboard — API layer public surface
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Screen agents import everything they need from `@/api`: typed request/response
// models, the raw endpoint clients (for imperative use), and the vue-query
// composables (for declarative, cache-aware data fetching).
// =============================================================================

export * from './types'
export { ApiError } from './ApiError'
export { http, request, configureHttpClient } from './http'
export { queryKeys } from './queryKeys'

// Raw endpoint clients
export { authApi } from './endpoints/auth'
export { usersApi } from './endpoints/users'
export { sitesApi, teamsApi, apiKeysApi } from './endpoints/sites'
export {
  analyticsApi,
  funnelsApi,
  goalsApi,
  segmentsApi,
  publicApi,
} from './endpoints/analytics'
export { reportsApi, scheduledReportsApi, alertsApi, exportsApi } from './endpoints/reporting'

// vue-query composables
export * from './composables/useAnalytics'
export * from './composables/useSites'
export * from './composables/useAccount'
export * from './composables/useReporting'
export * from './composables/usePublic'
