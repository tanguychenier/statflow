// =============================================================================
// Settings — site configuration model: validation, parsing, serialization
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Every rule the Settings screen enforces lives here as a pure function so the
// .vue files stay declarative bindings and the behaviour is deterministic and
// unit-testable without mounting a component or hitting the API. Limits mirror
// openapi.yaml (SiteSettings / TrackerConfig) and ADR-0009 (roles, public key).
// =============================================================================

import type { SiteSettings, TeamRole, TrackerConfig } from '@/api/types'

// ── Limits (openapi.yaml — SiteSettings / TrackerConfig) ─────────────────────

export const MAX_ALLOWED_DOMAINS = 50
export const MAX_EXCLUDED_IPS = 100
export const MAX_IGNORED_SELECTORS = 50
export const RETENTION_MIN_DAYS = 30
export const RETENTION_MAX_DAYS = 730
export const DEFAULT_RETENTION_DAYS = 365

/** Retention presets surfaced as a select; "custom" falls back to a number field. */
export const RETENTION_PRESETS = [30, 90, 180, 365, 730] as const

// ── Roles (ADR-0009 — four roles) ────────────────────────────────────────────

export const TEAM_ROLES: readonly TeamRole[] = ['owner', 'admin', 'editor', 'viewer']

/** Roles that can be assigned through the invite/role-change UI. Ownership is
 *  transferred via a dedicated flow, never picked from the role dropdown. */
export const ASSIGNABLE_ROLES: readonly TeamRole[] = ['admin', 'editor', 'viewer']

/** Capabilities required to mutate settings / manage members (ADR-0009 §3). */
const MANAGE_ROLES: ReadonlySet<TeamRole> = new Set<TeamRole>(['owner', 'admin'])

export function canManageSite(role: TeamRole | null | undefined): boolean {
  return role != null && MANAGE_ROLES.has(role)
}

export function canDeleteSite(role: TeamRole | null | undefined): boolean {
  return role === 'owner'
}

/** Visual weighting for the role badge. */
export function roleBadgeVariant(role: TeamRole): 'accent' | 'info' | 'default' {
  if (role === 'owner') return 'accent'
  if (role === 'admin') return 'info'
  return 'default'
}

// ── List editor parsing (excluded IPs, allowed domains) ──────────────────────

/**
 * Split a free-text blob (comma / newline / whitespace separated) into a list of
 * trimmed, de-duplicated, non-empty entries while preserving first-seen order.
 */
export function parseList(raw: string): string[] {
  const seen = new Set<string>()
  const out: string[] = []
  for (const token of raw.split(/[\s,]+/)) {
    const value = token.trim()
    if (value && !seen.has(value)) {
      seen.add(value)
      out.push(value)
    }
  }
  return out
}

// ── IP / CIDR validation ─────────────────────────────────────────────────────

function isIpv4Octet(part: string): boolean {
  if (!/^\d{1,3}$/.test(part)) return false
  const n = Number(part)
  // Reject leading-zero forms like "01" that some parsers treat as octal.
  return n <= 255 && String(n) === part
}

export function isValidIpv4(value: string): boolean {
  const parts = value.split('.')
  return parts.length === 4 && parts.every(isIpv4Octet)
}

export function isValidIpv6(value: string): boolean {
  // Reject IPv4-mapped and zone-id forms here; the common cases for an exclude
  // list are plain v6 addresses and the loopback "::1" / unspecified "::".
  if (value.includes('.') || value.includes('%')) return false
  if (!/^[0-9a-fA-F:]+$/.test(value)) return false
  const doubleColons = value.match(/::/g)?.length ?? 0
  if (doubleColons > 1) return false
  const groups = value.split(':').filter((g) => g !== '')
  if (groups.some((g) => g.length > 4)) return false
  if (doubleColons === 0) return value.split(':').length === 8
  // "::" compresses one or more zero groups, so fewer explicit groups are valid.
  return groups.length <= 7
}

/** Accepts a bare IP or a CIDR range (`ip/prefix`). */
export function isValidIpOrCidr(value: string): boolean {
  const [address, prefix, ...rest] = value.split('/')
  if (rest.length > 0) return false
  if (prefix !== undefined) {
    if (!/^\d{1,3}$/.test(prefix)) return false
    const bits = Number(prefix)
    const isV4 = isValidIpv4(address)
    const isV6 = isValidIpv6(address)
    if (!isV4 && !isV6) return false
    return bits <= (isV4 ? 32 : 128)
  }
  return isValidIpv4(address) || isValidIpv6(address)
}

// ── Domain / origin validation ───────────────────────────────────────────────

/**
 * A hostname, optionally a single leading `*.` wildcard (openapi.yaml allows
 * `*.example.com`). No scheme, port, or path — just the host label sequence.
 */
export function isValidDomainPattern(value: string): boolean {
  const host = value.startsWith('*.') ? value.slice(2) : value
  if (host.length === 0 || host.length > 253) return false
  if (host.includes('*')) return false
  const labels = host.split('.')
  if (labels.length < 2) return false
  const label = /^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/
  return labels.every((l) => label.test(l))
}

export interface ListValidation {
  /** Entries that fail the per-item validator. */
  invalid: string[]
  /** True when over the configured cap. */
  overLimit: boolean
  valid: boolean
}

export function validateList(
  items: string[],
  predicate: (value: string) => boolean,
  max: number,
): ListValidation {
  const invalid = items.filter((item) => !predicate(item))
  const overLimit = items.length > max
  return { invalid, overLimit, valid: invalid.length === 0 && !overLimit }
}

// ── Data retention ───────────────────────────────────────────────────────────

export function clampRetention(days: number): number {
  if (Number.isNaN(days)) return DEFAULT_RETENTION_DAYS
  return Math.min(RETENTION_MAX_DAYS, Math.max(RETENTION_MIN_DAYS, Math.round(days)))
}

export function isValidRetention(days: number): boolean {
  return (
    Number.isInteger(days) && days >= RETENTION_MIN_DAYS && days <= RETENTION_MAX_DAYS
  )
}

// ── Tracker snippet ──────────────────────────────────────────────────────────

/**
 * Build the paste-and-forget loading snippet (tracker-design.md §3.1). The
 * tracker is always served from the operator's own instance — there is no CDN
 * (ADR-0009) — so `origin` defaults to the dashboard's own origin. The public
 * `stk_` site key is embedded by design; it grants nothing beyond event submit.
 */
export function buildTrackerSnippet(siteKey: string, domain: string, origin: string): string {
  const src = `${origin.replace(/\/$/, '')}/sf/tracker.js`
  return [
    '<script>',
    '  window.statflow=window.statflow||{q:[]};',
    '  window.statflow.track=function(){window.statflow.q.push(arguments)};',
    '</script>',
    `<script src="${src}" defer data-site-key="${siteKey}" data-domain="${domain}"></script>`,
  ].join('\n')
}

// ── Tracker config defaults (openapi.yaml — TrackerConfig) ────────────────────

export function withTrackerDefaults(config: TrackerConfig | undefined): Required<
  Pick<
    TrackerConfig,
    | 'track_clicks'
    | 'track_scroll'
    | 'track_engagement_time'
    | 'track_outbound_links'
    | 'hash_based_routing'
  >
> {
  return {
    track_clicks: config?.track_clicks ?? true,
    track_scroll: config?.track_scroll ?? true,
    track_engagement_time: config?.track_engagement_time ?? true,
    track_outbound_links: config?.track_outbound_links ?? true,
    hash_based_routing: config?.hash_based_routing ?? false,
  }
}

// ── Settings draft (the editable shape behind the Tracking section) ───────────

export interface TrackingDraft {
  excludedIps: string[]
  allowedDomains: string[]
  dataRetentionDays: number
  stripQueryParams: boolean
  trackClicks: boolean
  trackScroll: boolean
  trackEngagementTime: boolean
  trackOutboundLinks: boolean
  hashBasedRouting: boolean
}

export function draftFromSettings(settings: SiteSettings | undefined): TrackingDraft {
  const tracker = withTrackerDefaults(settings?.tracker_config)
  return {
    excludedIps: settings?.excluded_ips ?? [],
    allowedDomains: settings?.allowed_domains ?? [],
    dataRetentionDays: settings?.data_retention_days ?? DEFAULT_RETENTION_DAYS,
    stripQueryParams: settings?.strip_query_params ?? false,
    trackClicks: tracker.track_clicks,
    trackScroll: tracker.track_scroll,
    trackEngagementTime: tracker.track_engagement_time,
    trackOutboundLinks: tracker.track_outbound_links,
    hashBasedRouting: tracker.hash_based_routing,
  }
}

/** PUT replaces the whole settings object (openapi.yaml), so preserve fields
 *  this screen does not edit (e.g. ignored_selectors, sampling_rate). */
export function settingsFromDraft(
  draft: TrackingDraft,
  base: SiteSettings | undefined,
): SiteSettings {
  return {
    ...base,
    excluded_ips: draft.excludedIps,
    allowed_domains: draft.allowedDomains,
    data_retention_days: clampRetention(draft.dataRetentionDays),
    strip_query_params: draft.stripQueryParams,
    custom_domain_enabled: base?.custom_domain_enabled ?? false,
    tracker_config: {
      ...base?.tracker_config,
      track_clicks: draft.trackClicks,
      track_scroll: draft.trackScroll,
      track_engagement_time: draft.trackEngagementTime,
      track_outbound_links: draft.trackOutboundLinks,
      hash_based_routing: draft.hashBasedRouting,
    },
  }
}

export interface TrackingValidation {
  ipsValid: boolean
  domainsValid: boolean
  retentionValid: boolean
  valid: boolean
}

export function validateTracking(draft: TrackingDraft): TrackingValidation {
  const ips = validateList(draft.excludedIps, isValidIpOrCidr, MAX_EXCLUDED_IPS)
  const domains = validateList(draft.allowedDomains, isValidDomainPattern, MAX_ALLOWED_DOMAINS)
  const retentionValid = isValidRetention(draft.dataRetentionDays)
  return {
    ipsValid: ips.valid,
    domainsValid: domains.valid,
    retentionValid,
    valid: ips.valid && domains.valid && retentionValid,
  }
}

// ── Danger-zone confirmation ─────────────────────────────────────────────────

/** Destructive actions require the user to type the exact site name. */
export function isDangerConfirmMatch(expected: string, typed: string): boolean {
  return expected.trim().length > 0 && typed.trim() === expected.trim()
}
