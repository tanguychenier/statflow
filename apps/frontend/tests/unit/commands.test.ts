// =============================================================================
// Statflow Dashboard — command palette command registry tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  NAVIGATION_COMMANDS,
  fuzzyMatch,
  optionDomId,
  activeDescendantId,
} from '@/components/CommandPalette/commands'

describe('fuzzyMatch', () => {
  it('matches an empty query against anything', () => {
    expect(fuzzyMatch('', 'Overview')).toBe(true)
  })

  it('matches a contiguous substring', () => {
    expect(fuzzyMatch('over', 'Overview')).toBe(true)
  })

  it('matches a non-contiguous subsequence', () => {
    expect(fuzzyMatch('ovw', 'Overview')).toBe(true)
  })

  it('is case-insensitive', () => {
    expect(fuzzyMatch('REAL', 'Realtime')).toBe(true)
  })

  it('rejects a query that is not a subsequence', () => {
    expect(fuzzyMatch('zzz', 'Overview')).toBe(false)
  })
})

describe('NAVIGATION_COMMANDS', () => {
  it('references only existing named routes', () => {
    const routeNames = new Set([
      'overview',
      'realtime',
      'heatmaps',
      'pages-sources',
      'funnels',
      'settings',
    ])
    for (const command of NAVIGATION_COMMANDS) {
      expect(command.to && routeNames.has(command.to)).toBe(true)
    }
  })

  it('places every navigation command in the navigate section', () => {
    expect(NAVIGATION_COMMANDS.every((c) => c.section === 'navigate')).toBe(true)
  })
})

describe('combobox active-descendant', () => {
  it('derives a stable, unique DOM id per option index', () => {
    expect(optionDomId(0)).toBe('command-palette-option-0')
    expect(optionDomId(3)).toBe('command-palette-option-3')
    expect(optionDomId(0)).not.toBe(optionDomId(1))
  })

  it('points aria-activedescendant at the active option id', () => {
    expect(activeDescendantId(0, 5)).toBe('command-palette-option-0')
    expect(activeDescendantId(2, 5)).toBe('command-palette-option-2')
  })

  it('is undefined when there are no results so the attribute is omitted', () => {
    expect(activeDescendantId(0, 0)).toBeUndefined()
  })

  it('is undefined when the active index falls outside the result range', () => {
    expect(activeDescendantId(-1, 3)).toBeUndefined()
    expect(activeDescendantId(5, 3)).toBeUndefined()
  })
})
