// =============================================================================
// Statflow Dashboard — Command palette command registry
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The navigation command set, derived from the router's named routes so the
// palette and the sidebar never drift. Actions (export, new funnel, …) are
// appended by the host via the `extraCommands` prop on the palette.
// =============================================================================

export type CommandSection = 'recent' | 'navigate' | 'actions'

export interface Command {
  id: string
  section: CommandSection
  /** i18n key resolved at render time. */
  labelKey: string
  /** Lucide icon name token used by the palette renderer. */
  icon: string
  /** Route name to navigate to, or a callback for actions. */
  to?: string
  run?: () => void
  shortcut?: string
}

export const NAVIGATION_COMMANDS: Command[] = [
  { id: 'overview', section: 'navigate', labelKey: 'nav.overview', icon: 'LayoutDashboard', to: 'overview' },
  { id: 'realtime', section: 'navigate', labelKey: 'nav.realtime', icon: 'Radio', to: 'realtime', shortcut: '⌘R' },
  { id: 'heatmaps', section: 'navigate', labelKey: 'nav.heatmaps', icon: 'Flame', to: 'heatmaps' },
  { id: 'pages-sources', section: 'navigate', labelKey: 'nav.pagesAndSources', icon: 'FileBarChart', to: 'pages-sources' },
  { id: 'funnels', section: 'navigate', labelKey: 'nav.funnels', icon: 'Filter', to: 'funnels', shortcut: '⌘F' },
  { id: 'settings', section: 'navigate', labelKey: 'nav.settings', icon: 'Settings2', to: 'settings' },
]

/** Stable DOM id for the option at `flatIndex`, used for aria-activedescendant. */
export function optionDomId(flatIndex: number): string {
  return `command-palette-option-${flatIndex}`
}

/**
 * The aria-activedescendant value for the combobox: the id of the active option,
 * or undefined when there is no result to point at (so the attribute is omitted).
 */
export function activeDescendantId(activeIndex: number, resultCount: number): string | undefined {
  if (resultCount === 0 || activeIndex < 0 || activeIndex >= resultCount) return undefined
  return optionDomId(activeIndex)
}

/** Case-insensitive subsequence match — the lightweight fuzzy used by the palette. */
export function fuzzyMatch(query: string, text: string): boolean {
  if (!query) return true
  const haystack = text.toLowerCase()
  const needle = query.toLowerCase()
  let cursor = 0
  for (const char of haystack) {
    if (char === needle[cursor]) cursor += 1
    if (cursor === needle.length) return true
  }
  return cursor === needle.length
}
