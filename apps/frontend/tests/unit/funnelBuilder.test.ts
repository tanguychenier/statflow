// =============================================================================
// funnelBuilder — draft model, validation & serialization tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import {
  CONVERSION_WINDOW_DAYS,
  DEFAULT_CONVERSION_WINDOW_DAYS,
  MAX_NAME_LENGTH,
  MAX_STEPS,
  MIN_STEPS,
  canAddStep,
  canRemoveStep,
  createDraftStep,
  createEmptyDraft,
  draftFromFunnel,
  reorderStep,
  toCreateRequest,
  validateDraft,
  type FunnelDraft,
} from '@/components/funnels/funnelBuilder'
import type { Funnel } from '@/api/types'

function validDraft(): FunnelDraft {
  const draft = createEmptyDraft()
  draft.name = 'Checkout'
  draft.steps[0].urlPattern = '/product'
  draft.steps[1].urlPattern = '/cart'
  return draft
}

describe('createEmptyDraft', () => {
  it('starts with the minimum number of steps and defaults', () => {
    const draft = createEmptyDraft()
    expect(draft.steps).toHaveLength(MIN_STEPS)
    expect(draft.name).toBe('')
    expect(draft.conversionWindowDays).toBe(DEFAULT_CONVERSION_WINDOW_DAYS)
    expect(draft.countMode).toBe('unique_users')
  })

  it('gives each step a unique client id', () => {
    const draft = createEmptyDraft()
    expect(draft.steps[0].uid).not.toBe(draft.steps[1].uid)
  })
})

describe('createDraftStep', () => {
  it('defaults to a pageview trigger', () => {
    expect(createDraftStep().triggerType).toBe('pageview')
  })

  it('honours an explicit trigger type', () => {
    expect(createDraftStep('event').triggerType).toBe('event')
  })
})

describe('canAddStep / canRemoveStep', () => {
  it('allows adding below the max', () => {
    expect(canAddStep(createEmptyDraft())).toBe(true)
  })

  it('forbids adding at the max', () => {
    const draft = createEmptyDraft()
    draft.steps = Array.from({ length: MAX_STEPS }, () => createDraftStep())
    expect(canAddStep(draft)).toBe(false)
  })

  it('forbids removing at the minimum', () => {
    expect(canRemoveStep(createEmptyDraft())).toBe(false)
  })

  it('allows removing above the minimum', () => {
    const draft = createEmptyDraft()
    draft.steps.push(createDraftStep())
    expect(canRemoveStep(draft)).toBe(true)
  })
})

describe('validateDraft', () => {
  it('accepts a well-formed draft', () => {
    const result = validateDraft(validDraft())
    expect(result.valid).toBe(true)
    expect(result.nameError).toBeNull()
    expect(result.stepsError).toBeNull()
    expect(result.stepErrors).toEqual({})
  })

  it('flags a missing name', () => {
    const draft = validDraft()
    draft.name = '   '
    expect(validateDraft(draft).nameError).toBe('required')
  })

  it('flags an over-long name', () => {
    const draft = validDraft()
    draft.name = 'x'.repeat(MAX_NAME_LENGTH + 1)
    expect(validateDraft(draft).nameError).toBe('tooLong')
  })

  it('flags too few steps', () => {
    const draft = validDraft()
    draft.steps = [draft.steps[0]]
    expect(validateDraft(draft).stepsError).toBe('tooFew')
  })

  it('flags a pageview step with an empty url', () => {
    const draft = validDraft()
    draft.steps[1].urlPattern = '  '
    const result = validateDraft(draft)
    expect(result.valid).toBe(false)
    expect(result.stepErrors[draft.steps[1].uid]).toEqual({ field: 'matcher' })
  })

  it('flags an event step with an empty event name', () => {
    const draft = validDraft()
    draft.steps[1].triggerType = 'event'
    draft.steps[1].eventName = ''
    expect(validateDraft(draft).stepErrors[draft.steps[1].uid]).toEqual({ field: 'matcher' })
  })

  it('uses the event name as matcher for event steps', () => {
    const draft = validDraft()
    draft.steps[1].triggerType = 'event'
    draft.steps[1].eventName = 'purchase'
    expect(validateDraft(draft).valid).toBe(true)
  })
})

describe('toCreateRequest', () => {
  it('serializes a valid draft, re-indexing steps from 0', () => {
    const draft = validDraft()
    const body = toCreateRequest(draft)
    expect(body.name).toBe('Checkout')
    expect(body.steps.map((s) => s.step_index)).toEqual([0, 1])
    expect(body.steps[0].url_pattern).toBe('/product')
    expect(body.steps[0].trigger_type).toBe('pageview')
  })

  it('drops the unused matcher field per trigger type', () => {
    const draft = validDraft()
    draft.steps[1].triggerType = 'event'
    draft.steps[1].eventName = 'purchase'
    const body = toCreateRequest(draft)
    expect(body.steps[1].event_name).toBe('purchase')
    expect(body.steps[1].url_pattern).toBeUndefined()
  })

  it('includes a trimmed label only when present', () => {
    const draft = validDraft()
    draft.steps[0].label = '  View product  '
    const body = toCreateRequest(draft)
    expect(body.steps[0].label).toBe('View product')
    expect(body.steps[1].label).toBeUndefined()
  })

  it('carries step filters when present', () => {
    const draft = validDraft()
    draft.steps[0].filters = [{ property: 'device_type', operator: 'eq', value: 'mobile' }]
    const body = toCreateRequest(draft)
    expect(body.steps[0].filters).toHaveLength(1)
    expect(body.steps[1].filters).toBeUndefined()
  })

  it('throws on an invalid draft', () => {
    const draft = createEmptyDraft()
    expect(() => toCreateRequest(draft)).toThrow()
  })
})

describe('reorderStep', () => {
  it('swaps a step with its neighbour', () => {
    const draft = validDraft()
    const [first, second] = draft.steps
    const next = reorderStep(draft, 0, 1)
    expect(next[0]).toBe(second)
    expect(next[1]).toBe(first)
  })

  it('is a no-op past the start', () => {
    const draft = validDraft()
    expect(reorderStep(draft, 0, -1)).toBe(draft.steps)
  })

  it('is a no-op past the end', () => {
    const draft = validDraft()
    expect(reorderStep(draft, draft.steps.length - 1, 1)).toBe(draft.steps)
  })
})

describe('draftFromFunnel', () => {
  const funnel: Funnel = {
    id: 'f1',
    site_id: 's1',
    name: 'Signup',
    steps: [
      { step_index: 1, label: 'Pricing', trigger_type: 'pageview', url_pattern: '/pricing' },
      { step_index: 0, label: 'Home', trigger_type: 'pageview', url_pattern: '/' },
    ],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
  }

  it('hydrates and sorts steps by index', () => {
    const draft = draftFromFunnel(funnel)
    expect(draft.name).toBe('Signup')
    expect(draft.steps.map((s) => s.urlPattern)).toEqual(['/', '/pricing'])
  })

  it('pads to the minimum step count when the funnel is under-specified', () => {
    const draft = draftFromFunnel({ ...funnel, steps: [funnel.steps[0]] })
    expect(draft.steps.length).toBeGreaterThanOrEqual(MIN_STEPS)
  })

  it('maps event steps and missing labels', () => {
    const draft = draftFromFunnel({
      ...funnel,
      steps: [
        { step_index: 0, trigger_type: 'event', event_name: 'signup' },
        { step_index: 1, trigger_type: 'pageview', url_pattern: '/done' },
      ],
    })
    expect(draft.steps[0].triggerType).toBe('event')
    expect(draft.steps[0].eventName).toBe('signup')
    expect(draft.steps[0].label).toBe('')
  })
})

describe('CONVERSION_WINDOW_DAYS', () => {
  it('includes the default window', () => {
    expect(CONVERSION_WINDOW_DAYS).toContain(DEFAULT_CONVERSION_WINDOW_DAYS)
  })
})
