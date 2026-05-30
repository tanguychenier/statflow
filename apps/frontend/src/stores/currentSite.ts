// =============================================================================
// Statflow Dashboard — Current site store
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Tracks which site the dashboard is currently scoped to. Every analytics query
// is keyed on the current site id, so a switch here invalidates and re-fetches
// every site-scoped query. The last selected site is persisted so reloads land
// the user back on the same property.
// =============================================================================

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { Site } from '@/api/types'

const STORAGE_KEY = 'sf-current-site'

export const useCurrentSiteStore = defineStore('currentSite', () => {
  const sites = ref<Site[]>([])
  const currentSiteId = ref<string | null>(localStorage.getItem(STORAGE_KEY))

  const currentSite = computed<Site | null>(
    () => sites.value.find((site) => site.id === currentSiteId.value) ?? null,
  )

  const hasSite = computed(() => currentSite.value !== null)

  function setSites(next: Site[]) {
    sites.value = next
    // Fall back to the first site when the persisted id is stale or unset.
    const stillValid = next.some((site) => site.id === currentSiteId.value)
    if (!stillValid) {
      selectSite(next.length > 0 ? next[0].id : null)
    }
  }

  function selectSite(siteId: string | null) {
    currentSiteId.value = siteId
    if (siteId) {
      localStorage.setItem(STORAGE_KEY, siteId)
    } else {
      localStorage.removeItem(STORAGE_KEY)
    }
  }

  function reset() {
    sites.value = []
    selectSite(null)
  }

  return { sites, currentSiteId, currentSite, hasSite, setSites, selectSite, reset }
})
