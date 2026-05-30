// =============================================================================
// Settings — settingsModel pure-function tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  ASSIGNABLE_ROLES,
  DEFAULT_RETENTION_DAYS,
  MAX_ALLOWED_DOMAINS,
  MAX_EXCLUDED_IPS,
  RETENTION_MAX_DAYS,
  RETENTION_MIN_DAYS,
  TEAM_ROLES,
  buildTrackerSnippet,
  canDeleteSite,
  canManageSite,
  clampRetention,
  draftFromSettings,
  isDangerConfirmMatch,
  isValidDomainPattern,
  isValidIpOrCidr,
  isValidIpv4,
  isValidIpv6,
  isValidRetention,
  parseList,
  roleBadgeVariant,
  settingsFromDraft,
  validateList,
  validateTracking,
  withTrackerDefaults,
  type TrackingDraft,
} from '@/components/settings/settingsModel'
import type { SiteSettings } from '@/api/types'

describe('roles', () => {
  it('lists the four ADR-0009 roles', () => {
    expect(TEAM_ROLES).toEqual(['owner', 'admin', 'editor', 'viewer'])
  })

  it('never offers owner as an assignable role', () => {
    expect(ASSIGNABLE_ROLES).not.toContain('owner')
    expect(ASSIGNABLE_ROLES).toEqual(['admin', 'editor', 'viewer'])
  })

  it('grants manage to owner and admin only', () => {
    expect(canManageSite('owner')).toBe(true)
    expect(canManageSite('admin')).toBe(true)
    expect(canManageSite('editor')).toBe(false)
    expect(canManageSite('viewer')).toBe(false)
    expect(canManageSite(null)).toBe(false)
    expect(canManageSite(undefined)).toBe(false)
  })

  it('grants delete to the owner only', () => {
    expect(canDeleteSite('owner')).toBe(true)
    expect(canDeleteSite('admin')).toBe(false)
    expect(canDeleteSite(null)).toBe(false)
  })

  it('maps roles to badge variants', () => {
    expect(roleBadgeVariant('owner')).toBe('accent')
    expect(roleBadgeVariant('admin')).toBe('info')
    expect(roleBadgeVariant('editor')).toBe('default')
    expect(roleBadgeVariant('viewer')).toBe('default')
  })
})

describe('parseList', () => {
  it('splits on commas, whitespace and newlines', () => {
    expect(parseList('a, b\nc  d')).toEqual(['a', 'b', 'c', 'd'])
  })

  it('trims, drops empties and de-duplicates preserving order', () => {
    expect(parseList(' a ,, a , b ,a')).toEqual(['a', 'b'])
  })

  it('returns an empty list for blank input', () => {
    expect(parseList('   \n  ')).toEqual([])
    expect(parseList('')).toEqual([])
  })
})

describe('isValidIpv4', () => {
  it('accepts well-formed addresses', () => {
    expect(isValidIpv4('192.168.0.1')).toBe(true)
    expect(isValidIpv4('0.0.0.0')).toBe(true)
    expect(isValidIpv4('255.255.255.255')).toBe(true)
  })

  it('rejects out-of-range, leading-zero, short and non-numeric forms', () => {
    expect(isValidIpv4('256.0.0.1')).toBe(false)
    expect(isValidIpv4('192.168.0')).toBe(false)
    expect(isValidIpv4('1.2.3.4.5')).toBe(false)
    expect(isValidIpv4('01.2.3.4')).toBe(false)
    expect(isValidIpv4('a.b.c.d')).toBe(false)
    expect(isValidIpv4('1.2.3.999')).toBe(false)
  })
})

describe('isValidIpv6', () => {
  it('accepts full and compressed addresses', () => {
    expect(isValidIpv6('2001:0db8:0000:0000:0000:0000:0000:0001')).toBe(true)
    expect(isValidIpv6('2001:db8::1')).toBe(true)
    expect(isValidIpv6('::1')).toBe(true)
    expect(isValidIpv6('::')).toBe(true)
  })

  it('rejects malformed addresses', () => {
    expect(isValidIpv6('2001:db8::1::2')).toBe(false)
    expect(isValidIpv6('gggg::1')).toBe(false)
    expect(isValidIpv6('12345::1')).toBe(false)
    expect(isValidIpv6('::ffff:1.2.3.4')).toBe(false)
    expect(isValidIpv6('fe80::1%eth0')).toBe(false)
    expect(isValidIpv6('2001:db8:1:2:3:4:5')).toBe(false)
  })
})

describe('isValidIpOrCidr', () => {
  it('accepts bare IPs and CIDR ranges', () => {
    expect(isValidIpOrCidr('10.0.0.1')).toBe(true)
    expect(isValidIpOrCidr('10.0.0.0/8')).toBe(true)
    expect(isValidIpOrCidr('2001:db8::/32')).toBe(true)
    expect(isValidIpOrCidr('::1/128')).toBe(true)
  })

  it('rejects bad prefixes and malformed ranges', () => {
    expect(isValidIpOrCidr('10.0.0.0/33')).toBe(false)
    expect(isValidIpOrCidr('2001:db8::/129')).toBe(false)
    expect(isValidIpOrCidr('10.0.0.0/')).toBe(false)
    expect(isValidIpOrCidr('10.0.0.0/8/8')).toBe(false)
    expect(isValidIpOrCidr('notanip/8')).toBe(false)
    expect(isValidIpOrCidr('10.0.0.0/abc')).toBe(false)
  })
})

describe('isValidDomainPattern', () => {
  it('accepts hostnames and a single leading wildcard', () => {
    expect(isValidDomainPattern('example.com')).toBe(true)
    expect(isValidDomainPattern('sub.example.co.uk')).toBe(true)
    expect(isValidDomainPattern('*.example.com')).toBe(true)
    expect(isValidDomainPattern('my-site.io')).toBe(true)
  })

  it('rejects schemes, ports, paths, bare labels and embedded wildcards', () => {
    expect(isValidDomainPattern('https://example.com')).toBe(false)
    expect(isValidDomainPattern('example.com:8080')).toBe(false)
    expect(isValidDomainPattern('example.com/path')).toBe(false)
    expect(isValidDomainPattern('localhost')).toBe(false)
    expect(isValidDomainPattern('foo.*.com')).toBe(false)
    expect(isValidDomainPattern('')).toBe(false)
    expect(isValidDomainPattern('-bad.com')).toBe(false)
    expect(isValidDomainPattern(`${'a'.repeat(254)}.com`)).toBe(false)
  })
})

describe('validateList', () => {
  it('reports invalid entries and respects the limit', () => {
    const ok = validateList(['10.0.0.1', '10.0.0.2'], isValidIpOrCidr, 5)
    expect(ok.valid).toBe(true)
    expect(ok.invalid).toEqual([])
    expect(ok.overLimit).toBe(false)
  })

  it('flags invalid items', () => {
    const result = validateList(['10.0.0.1', 'nope'], isValidIpOrCidr, 5)
    expect(result.valid).toBe(false)
    expect(result.invalid).toEqual(['nope'])
  })

  it('flags over-limit lists', () => {
    const result = validateList(['a', 'b', 'c'], () => true, 2)
    expect(result.overLimit).toBe(true)
    expect(result.valid).toBe(false)
  })
})

describe('retention', () => {
  it('clamps to the allowed window and rounds', () => {
    expect(clampRetention(10)).toBe(RETENTION_MIN_DAYS)
    expect(clampRetention(9999)).toBe(RETENTION_MAX_DAYS)
    expect(clampRetention(100.6)).toBe(101)
  })

  it('falls back to the default for NaN', () => {
    expect(clampRetention(Number.NaN)).toBe(DEFAULT_RETENTION_DAYS)
  })

  it('validates integer days within range', () => {
    expect(isValidRetention(30)).toBe(true)
    expect(isValidRetention(730)).toBe(true)
    expect(isValidRetention(29)).toBe(false)
    expect(isValidRetention(731)).toBe(false)
    expect(isValidRetention(100.5)).toBe(false)
  })
})

describe('buildTrackerSnippet', () => {
  it('embeds the public key and domain and points at the operator origin', () => {
    const snippet = buildTrackerSnippet('stk_abc', 'example.com', 'https://app.example.com')
    expect(snippet).toContain('data-site-key="stk_abc"')
    expect(snippet).toContain('data-domain="example.com"')
    expect(snippet).toContain('src="https://app.example.com/sf/tracker.js"')
    expect(snippet).toContain('window.statflow')
    // No external CDN reference (ADR-0009 — operator-hosted tracker only).
    expect(snippet).not.toContain('cdn.statflow')
  })

  it('normalises a trailing slash on the origin', () => {
    const snippet = buildTrackerSnippet('stk_x', 'example.com', 'https://app.example.com/')
    expect(snippet).toContain('https://app.example.com/sf/tracker.js')
    expect(snippet).not.toContain('com//sf')
  })
})

describe('withTrackerDefaults', () => {
  it('applies spec defaults when config is missing', () => {
    expect(withTrackerDefaults(undefined)).toEqual({
      track_clicks: true,
      track_scroll: true,
      track_engagement_time: true,
      track_outbound_links: true,
      hash_based_routing: false,
    })
  })

  it('preserves explicit false values', () => {
    const result = withTrackerDefaults({ track_clicks: false, hash_based_routing: true })
    expect(result.track_clicks).toBe(false)
    expect(result.hash_based_routing).toBe(true)
  })
})

describe('draftFromSettings / settingsFromDraft round trip', () => {
  it('seeds defaults from an empty settings object', () => {
    const draft = draftFromSettings({})
    expect(draft.dataRetentionDays).toBe(DEFAULT_RETENTION_DAYS)
    expect(draft.excludedIps).toEqual([])
    expect(draft.allowedDomains).toEqual([])
    expect(draft.stripQueryParams).toBe(false)
    expect(draft.trackClicks).toBe(true)
  })

  it('seeds from undefined settings', () => {
    const draft = draftFromSettings(undefined)
    expect(draft.dataRetentionDays).toBe(DEFAULT_RETENTION_DAYS)
  })

  it('maps loaded settings into the draft', () => {
    const settings: SiteSettings = {
      excluded_ips: ['10.0.0.1'],
      allowed_domains: ['example.com'],
      data_retention_days: 90,
      strip_query_params: true,
      tracker_config: { track_scroll: false, hash_based_routing: true },
    }
    const draft = draftFromSettings(settings)
    expect(draft.excludedIps).toEqual(['10.0.0.1'])
    expect(draft.allowedDomains).toEqual(['example.com'])
    expect(draft.dataRetentionDays).toBe(90)
    expect(draft.stripQueryParams).toBe(true)
    expect(draft.trackScroll).toBe(false)
    expect(draft.hashBasedRouting).toBe(true)
  })

  it('serialises a draft back to settings, clamping retention', () => {
    const draft: TrackingDraft = {
      excludedIps: ['10.0.0.1'],
      allowedDomains: ['example.com'],
      dataRetentionDays: 5,
      stripQueryParams: true,
      trackClicks: false,
      trackScroll: true,
      trackEngagementTime: false,
      trackOutboundLinks: true,
      hashBasedRouting: true,
    }
    const settings = settingsFromDraft(draft, undefined)
    expect(settings.excluded_ips).toEqual(['10.0.0.1'])
    expect(settings.data_retention_days).toBe(RETENTION_MIN_DAYS)
    expect(settings.strip_query_params).toBe(true)
    expect(settings.tracker_config?.track_clicks).toBe(false)
    expect(settings.tracker_config?.hash_based_routing).toBe(true)
  })

  it('preserves fields the screen does not edit (PUT replaces whole object)', () => {
    const base: SiteSettings = {
      custom_domain_enabled: true,
      tracker_config: { sampling_rate: 0.25, ignored_selectors: ['.private'] },
    }
    const draft = draftFromSettings(base)
    const out = settingsFromDraft(draft, base)
    expect(out.custom_domain_enabled).toBe(true)
    expect(out.tracker_config?.sampling_rate).toBe(0.25)
    expect(out.tracker_config?.ignored_selectors).toEqual(['.private'])
  })
})

describe('validateTracking', () => {
  const base: TrackingDraft = draftFromSettings({})

  it('passes for a clean draft', () => {
    const result = validateTracking({ ...base, excludedIps: ['10.0.0.1'], allowedDomains: ['x.com'] })
    expect(result.valid).toBe(true)
  })

  it('fails on invalid IPs', () => {
    const result = validateTracking({ ...base, excludedIps: ['nope'] })
    expect(result.ipsValid).toBe(false)
    expect(result.valid).toBe(false)
  })

  it('fails on invalid domains', () => {
    const result = validateTracking({ ...base, allowedDomains: ['no_dot'] })
    expect(result.domainsValid).toBe(false)
    expect(result.valid).toBe(false)
  })

  it('fails on out-of-range retention', () => {
    const result = validateTracking({ ...base, dataRetentionDays: 1 })
    expect(result.retentionValid).toBe(false)
    expect(result.valid).toBe(false)
  })

  it('fails when over the IP or domain caps', () => {
    const tooManyIps = Array.from({ length: MAX_EXCLUDED_IPS + 1 }, (_, i) => `10.0.0.${i % 255}`)
    const tooManyDomains = Array.from({ length: MAX_ALLOWED_DOMAINS + 1 }, (_, i) => `s${i}.example.com`)
    expect(validateTracking({ ...base, excludedIps: tooManyIps }).ipsValid).toBe(false)
    expect(validateTracking({ ...base, allowedDomains: tooManyDomains }).domainsValid).toBe(false)
  })
})

describe('isDangerConfirmMatch', () => {
  it('requires an exact, trimmed match', () => {
    expect(isDangerConfirmMatch('My Site', 'My Site')).toBe(true)
    expect(isDangerConfirmMatch('My Site', '  My Site  ')).toBe(true)
    expect(isDangerConfirmMatch('My Site', 'my site')).toBe(false)
    expect(isDangerConfirmMatch('My Site', 'My')).toBe(false)
  })

  it('never matches when the expected name is blank', () => {
    expect(isDangerConfirmMatch('', '')).toBe(false)
    expect(isDangerConfirmMatch('   ', '   ')).toBe(false)
  })
})
