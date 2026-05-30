// =============================================================================
// Statflow Dashboard — useSidebarState composable
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Persisted collapsed/expanded state for the navigation sidebar (components.md
// §9.2). A module-level singleton ref keeps every consumer (shell, topbar
// toggle) in sync without prop drilling, and survives reloads via localStorage.
// =============================================================================

import { ref, watch } from 'vue'

const STORAGE_KEY = 'sf-sidebar-collapsed'

function readInitial(): boolean {
  return localStorage.getItem(STORAGE_KEY) === 'true'
}

const collapsed = ref<boolean>(readInitial())

watch(collapsed, (value) => {
  localStorage.setItem(STORAGE_KEY, String(value))
})

export function useSidebarState() {
  function toggle() {
    collapsed.value = !collapsed.value
  }

  function setCollapsed(value: boolean) {
    collapsed.value = value
  }

  return { collapsed, toggle, setCollapsed }
}
