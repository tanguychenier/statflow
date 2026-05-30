// =============================================================================
// Statflow Dashboard — API contract types
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Hand-authored from docs/api/openapi.yaml (v1.0.0). These mirror the wire
// schemas one-to-one; they are the single TypeScript source of truth for every
// request/response body the dashboard exchanges with the backend.
// =============================================================================

// ── Shared ──────────────────────────────────────────────────────────────────

export interface DateRangeDto {
  from: string
  to: string
}

export interface Pagination {
  next_cursor: string | null
  prev_cursor: string | null
  has_next: boolean
  has_prev: boolean
  limit: number
}

export interface PaginatedResponse<T> {
  data: T[]
  pagination: Pagination
}

export interface FieldError {
  field?: string
  code?: string
  message?: string
}

/** RFC 9457 Problem Details — every 4xx/5xx response body. */
export interface ProblemDetails {
  type: string
  title: string
  status: number
  detail?: string
  instance?: string
  trace_id: string
  errors?: FieldError[]
}

// ── Auth ──────────────────────────────────────────────────────────────────

export interface TokenResponse {
  access_token: string
  token_type: 'Bearer'
  expires_in: number
}

export interface LoginRequest {
  email: string
  password: string
}

export interface RegisterRequest {
  email: string
  password: string
  name: string
}

export interface ForgotPasswordRequest {
  email: string
}

export interface ResetPasswordRequest {
  token: string
  new_password: string
}

// ── Users ──────────────────────────────────────────────────────────────────

export interface User {
  id: string
  email: string
  name: string
  avatar_url?: string
  created_at: string
  updated_at: string
}

export interface UserUpdateRequest {
  name?: string
  email?: string
}

export interface ChangePasswordRequest {
  current_password: string
  new_password: string
}

// ── Teams ──────────────────────────────────────────────────────────────────

export type TeamRole = 'owner' | 'admin' | 'editor' | 'viewer'

export interface Team {
  id: string
  name: string
  slug: string
  member_count: number
  site_count: number
  current_user_role: TeamRole
  created_at: string
}

export interface TeamCreateRequest {
  name: string
}

export interface TeamUpdateRequest {
  name?: string
}

export type TeamMemberStatus = 'active' | 'invited' | 'suspended'

export interface TeamMember {
  id: string
  user_id: string
  email: string
  name: string
  role: TeamRole
  status: TeamMemberStatus
  joined_at: string
}

export interface InviteTeamMemberRequest {
  email: string
  role: TeamRole
}

export interface UpdateTeamMemberRoleRequest {
  role: TeamRole
}

// ── API keys ─────────────────────────────────────────────────────────────────

export type ApiKeyScope =
  | 'analytics:read'
  | 'sites:read'
  | 'sites:write'
  | 'reports:read'
  | 'reports:write'

export interface ApiKey {
  id: string
  name: string
  key_prefix: string
  scopes: ApiKeyScope[]
  site_ids: string[]
  expires_at: string | null
  last_used_at: string | null
  created_at: string
}

/** Returned only by createApiKey — `raw_key` is shown once. */
export interface ApiKeyWithSecret extends ApiKey {
  raw_key: string
}

export interface ApiKeyCreateRequest {
  name: string
  scopes: ApiKeyScope[]
  site_ids?: string[]
  expires_at?: string | null
}

// ── Sites ──────────────────────────────────────────────────────────────────

export interface Site {
  id: string
  team_id: string
  name: string
  domain: string
  tracker_key: string
  timezone: string
  created_at: string
  updated_at: string
}

export interface SiteCreateRequest {
  team_id: string
  name: string
  domain: string
  timezone?: string
}

export interface SiteUpdateRequest {
  name?: string
  domain?: string
  timezone?: string
}

export interface TrackerConfig {
  track_clicks?: boolean
  track_scroll?: boolean
  track_engagement_time?: boolean
  track_outbound_links?: boolean
  hash_based_routing?: boolean
  ignored_selectors?: string[]
  sampling_rate?: number
}

export interface SiteSettings {
  allowed_domains?: string[]
  excluded_ips?: string[]
  data_retention_days?: number
  strip_query_params?: boolean
  custom_domain_enabled?: boolean
  tracker_config?: TrackerConfig
}

export interface RotateTrackerKeyResponse {
  tracker_key: string
  old_key_valid_until: string
}

export interface ListSitesParams {
  cursor?: string
  limit?: number
  team_id?: string
  search?: string
}

// ── Analytics — query model ─────────────────────────────────────────────────

export type MetricName =
  | 'visitors'
  | 'pageviews'
  | 'sessions'
  | 'bounce_rate'
  | 'avg_duration'
  | 'events'
  | 'conversion_rate'

export type ComparePeriod = 'previous_period' | 'previous_year'

export type FilterOperator =
  | 'eq'
  | 'neq'
  | 'in'
  | 'not_in'
  | 'contains'
  | 'not_contains'
  | 'starts_with'
  | 'gt'
  | 'gte'
  | 'lt'
  | 'lte'

export type FilterValue = string | number | boolean | Array<string | number>

export interface Filter {
  property: string
  operator: FilterOperator
  value: FilterValue
}

export type FilterCombination = 'and' | 'or'

export interface AnalyticsQueryBase {
  date_from: string
  date_to: string
  compare_period?: ComparePeriod
  filters?: Filter[]
  filter_combination?: FilterCombination
  segment_id?: string
  metrics?: MetricName[]
}

export type TimeInterval = 'minute' | 'hour' | 'day' | 'week' | 'month'

export interface TimeSeriesQuery extends AnalyticsQueryBase {
  interval?: TimeInterval
}

export type BreakdownProperty =
  | 'pathname'
  | 'referrer_domain'
  | 'utm_source'
  | 'utm_medium'
  | 'utm_campaign'
  | 'country'
  | 'region'
  | 'city'
  | 'device_type'
  | 'browser'
  | 'os'
  | 'language'
  | 'entry_page'
  | 'exit_page'
  | 'event_name'

export interface BreakdownQuery extends AnalyticsQueryBase {
  property: BreakdownProperty | string
  limit?: number
  sort_by?: MetricName
  sort_order?: 'asc' | 'desc'
}

// ── Analytics — responses ─────────────────────────────────────────────────

export type MetricValues = Partial<Record<MetricName, number>>

export interface AggregateMetricsResponse {
  period: DateRangeDto
  metrics: MetricValues
  comparison?: {
    period: DateRangeDto
    metrics: MetricValues
    change_pct: Partial<Record<MetricName, number | null>>
  }
}

export interface TimeSeriesBucket {
  bucket: string
  metrics: MetricValues
}

export interface TimeSeriesResponse {
  interval: TimeInterval
  period: DateRangeDto
  series: TimeSeriesBucket[]
}

export interface BreakdownRow {
  value: string
  metrics: MetricValues
}

export interface BreakdownResponse {
  property: string
  period: DateRangeDto
  rows: BreakdownRow[]
  total_rows: number
}

export interface RealtimeTopPage {
  pathname: string
  visitors: number
}

export interface RealtimeTopSource {
  referrer_domain: string
  visitors: number
}

export interface RealtimeResponse {
  current_visitors: number
  top_pages: RealtimeTopPage[]
  top_sources: RealtimeTopSource[]
  updated_at: string
}

export interface RealtimeEvent {
  timestamp: string
  event_name: string
  pathname: string
  referrer_source: string
  country: string
  device_type: string
  browser: string
}

// ── Funnels ──────────────────────────────────────────────────────────────────

export type FunnelTriggerType = 'pageview' | 'event'

export interface FunnelStep {
  step_index: number
  label?: string
  trigger_type: FunnelTriggerType
  url_pattern?: string
  event_name?: string
  filters?: Filter[]
}

export interface Funnel {
  id: string
  site_id: string
  name: string
  steps: FunnelStep[]
  created_at: string
  updated_at: string
}

export interface FunnelCreateRequest {
  name: string
  steps: FunnelStep[]
}

export interface FunnelQueryRequest {
  date_from: string
  date_to: string
  funnel_id: string
  conversion_window_days?: number
  filters?: Filter[]
  segment_id?: string
}

export interface FunnelResponseStep {
  step_index: number
  label: string
  event_name: string
  entered: number
  converted: number
  dropped: number
  conversion_rate_pct: number
  avg_time_to_convert_seconds: number
}

export interface FunnelResponse {
  period: DateRangeDto
  conversion_window_days: number
  steps: FunnelResponseStep[]
}

// ── Goals ──────────────────────────────────────────────────────────────────

export interface Goal {
  id: string
  site_id: string
  name: string
  trigger_type: FunnelTriggerType
  url_pattern: string | null
  event_name: string | null
  currency: string | null
  revenue_value: number | null
  created_at: string
}

export interface GoalCreateRequest {
  name: string
  trigger_type: FunnelTriggerType
  url_pattern?: string
  event_name?: string
  currency?: string
  revenue_value?: number
}

// ── Retention ─────────────────────────────────────────────────────────────

export interface RetentionQueryRequest {
  date_from: string
  date_to: string
  interval?: 'week' | 'month'
  return_event?: string
}

export interface RetentionCohortPeriod {
  period: number
  retained_count: number
  retention_pct: number
}

export interface RetentionCohort {
  cohort_date: string
  cohort_size: number
  retention: RetentionCohortPeriod[]
}

export interface RetentionResponse {
  interval: 'week' | 'month'
  cohorts: RetentionCohort[]
}

// ── Heatmaps ─────────────────────────────────────────────────────────────────

export type HeatmapType = 'click' | 'scroll'

export interface HeatmapQueryRequest {
  date_from: string
  date_to: string
  pathname: string
  viewport_width_min?: number
  viewport_width_max?: number
  heatmap_type?: HeatmapType
}

export interface HeatmapClickPoint {
  x_pct: number
  y_pct: number
  count: number
  weight: number
}

export interface HeatmapScrollDepth {
  depth_pct: number
  sessions_pct: number
}

export interface HeatmapResponse {
  pathname: string
  period: DateRangeDto
  heatmap_type: HeatmapType
  total_events: number
  click_points: HeatmapClickPoint[]
  scroll_depths?: HeatmapScrollDepth[]
}

// ── Segments ─────────────────────────────────────────────────────────────────

export interface Segment {
  id: string
  site_id: string
  name: string
  filters: Filter[]
  filter_combination: FilterCombination
  created_by: string
  created_at: string
}

export interface SegmentCreateRequest {
  name: string
  filters: Filter[]
  filter_combination?: FilterCombination
}

// ── Reporting ─────────────────────────────────────────────────────────────

export type ReportType =
  | 'aggregate'
  | 'timeseries'
  | 'breakdown'
  | 'funnel'
  | 'retention'
  | 'heatmap'

export interface SavedReport {
  id: string
  site_id: string
  name: string
  description?: string
  report_type: ReportType
  query: Record<string, unknown>
  created_by: string
  created_at: string
  updated_at: string
}

export interface SavedReportCreateRequest {
  name: string
  description?: string
  report_type: ReportType
  query: Record<string, unknown>
}

export interface ScheduledReport {
  id: string
  site_id: string
  saved_report_id: string | null
  name: string
  recipients: string[]
  schedule_cron: string
  timezone: string
  is_active: boolean
  last_sent_at: string | null
  next_send_at: string | null
  created_at: string
}

export interface ScheduledReportCreateRequest {
  name: string
  saved_report_id?: string
  recipients: string[]
  schedule_cron: string
  timezone: string
}

export interface ScheduledReportUpdateRequest {
  name?: string
  recipients?: string[]
  schedule_cron?: string
  timezone?: string
  is_active?: boolean
}

export type AlertCondition = 'above' | 'below' | 'change_pct_above' | 'change_pct_below'

export type NotificationChannelType = 'email' | 'webhook' | 'slack'

export interface NotificationChannel {
  type: NotificationChannelType
  email?: string
  webhook_url?: string
}

export interface Alert {
  id: string
  site_id: string
  name: string
  metric: MetricName
  condition: AlertCondition
  threshold: number
  comparison_period?: 'previous_day' | 'previous_week' | 'previous_month'
  filters?: Filter[]
  notification_channels: NotificationChannel[]
  is_active: boolean
  last_triggered_at: string | null
  created_at: string
}

export interface AlertCreateRequest {
  name: string
  metric: MetricName
  condition: AlertCondition
  threshold: number
  comparison_period?: 'previous_day' | 'previous_week' | 'previous_month'
  filters?: Filter[]
  notification_channels: NotificationChannel[]
}

export interface AlertUpdateRequest {
  name?: string
  condition?: AlertCondition
  threshold?: number
  is_active?: boolean
  notification_channels?: NotificationChannel[]
}

export type ExportFormat = 'csv' | 'ndjson'
export type ExportStatus = 'pending' | 'processing' | 'completed' | 'failed'

export interface Export {
  id: string
  site_id: string
  status: ExportStatus
  format: ExportFormat
  query: Record<string, unknown>
  row_count: number | null
  file_size_bytes: number | null
  download_url: string | null
  expires_at: string | null
  error_message: string | null
  created_at: string
  completed_at: string | null
}

export interface ExportCreateRequest {
  format: ExportFormat
  query: Record<string, unknown>
  notify_email?: string
}

// ── Public shared dashboard ─────────────────────────────────────────────────

export interface PublicOverviewParams {
  date_from?: string
  date_to?: string
  /** Sent as the X-Share-Password header for password-protected links. */
  sharePassword?: string
}
