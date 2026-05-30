// =============================================================================
// Statflow Dashboard — current site store tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useCurrentSiteStore } from '@/stores/currentSite'
import type { Site } from '@/api/types'

function site(id: string, name = id): Site {
  return {
    id,
    team_id: 't',
    name,
    domain: `${name}.com`,
    tracker_key: 'stk_x',
    timezone: 'UTC',
    created_at: '2025-01-01T00:00:00Z',
    updated_at: '2025-01-01T00:00:00Z',
  }
}

describe('useCurrentSiteStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('selects the first site when none is persisted', () => {
    const store = useCurrentSiteStore()
    store.setSites([site('a'), site('b')])
    expect(store.currentSiteId).toBe('a')
    expect(store.currentSite?.id).toBe('a')
    expect(store.hasSite).toBe(true)
  })

  it('keeps a still-valid persisted selection', () => {
    localStorage.setItem('sf-current-site', 'b')
    const store = useCurrentSiteStore()
    store.setSites([site('a'), site('b')])
    expect(store.currentSiteId).toBe('b')
  })

  it('falls back to the first site when the persisted id is stale', () => {
    localStorage.setItem('sf-current-site', 'gone')
    const store = useCurrentSiteStore()
    store.setSites([site('a')])
    expect(store.currentSiteId).toBe('a')
  })

  it('persists the selection and clears it on reset', () => {
    const store = useCurrentSiteStore()
    store.setSites([site('a'), site('b')])
    store.selectSite('b')
    expect(localStorage.getItem('sf-current-site')).toBe('b')
    store.reset()
    expect(store.currentSiteId).toBeNull()
    expect(store.hasSite).toBe(false)
    expect(localStorage.getItem('sf-current-site')).toBeNull()
  })

  it('selects null when the site list is empty', () => {
    const store = useCurrentSiteStore()
    store.setSites([])
    expect(store.currentSiteId).toBeNull()
  })
})
