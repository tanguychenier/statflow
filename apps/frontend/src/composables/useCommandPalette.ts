// =============================================================================
// Statflow Dashboard — useCommandPalette composable
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Open/close state for the ⌘K command palette plus a global keydown listener
// (components.md §8). A module-level singleton lets the topbar trigger and the
// palette dialog share one source of truth. The listener is reference-counted
// so it attaches once even if several components use the composable.
// =============================================================================

import { onBeforeUnmount, onMounted, ref } from 'vue'

const isOpen = ref(false)
let consumerCount = 0

function open() {
  isOpen.value = true
}

function close() {
  isOpen.value = false
}

function toggle() {
  isOpen.value = !isOpen.value
}

function onKeydown(event: KeyboardEvent) {
  const isShortcut = (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k'
  if (isShortcut) {
    event.preventDefault()
    toggle()
  }
}

export function useCommandPalette() {
  onMounted(() => {
    if (consumerCount === 0) {
      window.addEventListener('keydown', onKeydown)
    }
    consumerCount += 1
  })

  onBeforeUnmount(() => {
    consumerCount -= 1
    if (consumerCount <= 0) {
      window.removeEventListener('keydown', onKeydown)
      consumerCount = 0
    }
  })

  return { isOpen, open, close, toggle }
}
