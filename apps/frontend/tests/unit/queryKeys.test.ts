// =============================================================================
// Statflow Dashboard — query key registry tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { queryKeys } from '@/api/queryKeys'
import type { AnalyticsQueryBase } from '@/api/types'

describe('queryKeys', () => {
  it('namespaces analytics keys under the site so they invalidate together', () => {
    expect(queryKeys.analytics('site-1')).toEqual(['analytics', 'site-1'])
    const query: AnalyticsQueryBase = { date_from: '2025-06-01', date_to: '2025-06-30' }
    const aggregate = queryKeys.aggregate('site-1', query)
    expect(aggregate[0]).toBe('analytics')
    expect(aggregate[1]).toBe('site-1')
    expect(aggregate[2]).toBe('aggregate')
    expect(aggregate[3]).toEqual(query)
  })

  it('produces distinct keys for distinct date ranges', () => {
    const a = queryKeys.aggregate('s', { date_from: '2025-01-01', date_to: '2025-01-31' })
    const b = queryKeys.aggregate('s', { date_from: '2025-02-01', date_to: '2025-02-28' })
    expect(a).not.toEqual(b)
  })

  it('keys site-scoped resources under the site id', () => {
    expect(queryKeys.funnels('s')).toEqual(['sites', 's', 'funnels'])
    expect(queryKeys.funnel('s', 'f')).toEqual(['sites', 's', 'funnels', 'f'])
    expect(queryKeys.siteSettings('s')).toEqual(['sites', 's', 'settings'])
  })

  it('scopes site-list queries under a dedicated `list` segment', () => {
    expect(queryKeys.sitesList()).toEqual(['sites', 'list'])
    expect(queryKeys.sites()).toEqual(['sites', 'list', {}])
    expect(queryKeys.sites({ team_id: 't1' })).toEqual(['sites', 'list', { team_id: 't1' }])
  })

  it('keeps the list key from prefix-matching site detail or sub-resource keys', () => {
    const listPrefix = queryKeys.sitesList() // ['sites', 'list']
    const startsWith = (key: readonly unknown[]) =>
      listPrefix.every((segment, index) => key[index] === segment)

    // Detail and sub-resource keys must NOT be caught by invalidating the list.
    expect(startsWith(queryKeys.site('s'))).toBe(false)
    expect(startsWith(queryKeys.siteSettings('s'))).toBe(false)
    expect(startsWith(queryKeys.funnels('s'))).toBe(false)
    expect(startsWith(queryKeys.segments('s'))).toBe(false)
    expect(startsWith(queryKeys.savedReports('s'))).toBe(false)

    // Concrete list queries ARE caught.
    expect(startsWith(queryKeys.sites({ team_id: 't1' }))).toBe(true)
  })

  it('keys the public overview by token and date bounds', () => {
    expect(queryKeys.publicOverview('tok', '2025-06-01', '2025-06-30')).toEqual([
      'public',
      'tok',
      'overview',
      '2025-06-01',
      '2025-06-30',
    ])
  })
})
