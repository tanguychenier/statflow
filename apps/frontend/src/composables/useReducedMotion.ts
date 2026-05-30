// =============================================================================
// Statflow Dashboard — useReducedMotion Composable
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { ref, onMounted, onUnmounted } from 'vue'

/**
 * Reactively tracks the user's prefers-reduced-motion preference.
 * When true, all motion-decorative animations should be eliminated or
 * collapsed to near-instant durations.
 */
export function useReducedMotion() {
  const prefersReduced = ref(false)

  let mq: MediaQueryList | null = null

  const handler = (e: MediaQueryListEvent) => {
    prefersReduced.value = e.matches
  }

  onMounted(() => {
    mq = window.matchMedia('(prefers-reduced-motion: reduce)')
    prefersReduced.value = mq.matches
    mq.addEventListener('change', handler)
  })

  onUnmounted(() => {
    mq?.removeEventListener('change', handler)
  })

  return { prefersReduced }
}
