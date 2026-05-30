// =============================================================================
// Funnels — builder model: step editing, validation, serialization (screens.md §3.3)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The builder modal edits a "draft" shape that is friendlier to the form than
// the wire model (stable client ids for v-for, no step_index churn while
// reordering). This module owns every rule: how many steps are allowed, what
// makes a step valid, and how a draft becomes a FunnelCreateRequest. The .vue
// stays a thin two-way binding over these pure functions.
// =============================================================================

import type {
  Filter,
  Funnel,
  FunnelCreateRequest,
  FunnelStep,
  FunnelTriggerType,
} from '@/api/types'

/** Funnels must have at least two and at most sixteen steps (openapi.yaml). */
export const MIN_STEPS = 2
export const MAX_STEPS = 16
export const MAX_NAME_LENGTH = 200
export const MAX_LABEL_LENGTH = 100

/** Conversion-window presets offered in the builder (days). 0 = no limit. */
export const CONVERSION_WINDOW_DAYS = [1, 7, 14, 30, 60, 90] as const
export const DEFAULT_CONVERSION_WINDOW_DAYS = 30

export type CountMode = 'unique_users' | 'sessions'

export interface DraftStep {
  /** Client-only stable id for list rendering; never sent to the API. */
  uid: string
  label: string
  triggerType: FunnelTriggerType
  /** URL/path pattern — used when triggerType is `pageview`. */
  urlPattern: string
  /** Event name — used when triggerType is `event`. */
  eventName: string
  filters: Filter[]
}

export interface FunnelDraft {
  name: string
  steps: DraftStep[]
  conversionWindowDays: number
  countMode: CountMode
}

let uidCounter = 0
function nextUid(): string {
  uidCounter += 1
  return `step-${uidCounter}`
}

export function createDraftStep(triggerType: FunnelTriggerType = 'pageview'): DraftStep {
  return { uid: nextUid(), label: '', triggerType, urlPattern: '', eventName: '', filters: [] }
}

/** A fresh draft with the minimum number of empty steps. */
export function createEmptyDraft(): FunnelDraft {
  return {
    name: '',
    steps: [createDraftStep(), createDraftStep()],
    conversionWindowDays: DEFAULT_CONVERSION_WINDOW_DAYS,
    countMode: 'unique_users',
  }
}

/** Hydrate an editable draft from a persisted funnel (Edit flow). */
export function draftFromFunnel(funnel: Funnel): FunnelDraft {
  const steps = [...funnel.steps]
    .sort((a, b) => a.step_index - b.step_index)
    .map((step) => ({
      uid: nextUid(),
      label: step.label ?? '',
      triggerType: step.trigger_type,
      urlPattern: step.url_pattern ?? '',
      eventName: step.event_name ?? '',
      filters: step.filters ? step.filters.map((f) => ({ ...f })) : [],
    }))
  return {
    name: funnel.name,
    steps: steps.length >= MIN_STEPS ? steps : [...steps, createDraftStep()],
    conversionWindowDays: DEFAULT_CONVERSION_WINDOW_DAYS,
    countMode: 'unique_users',
  }
}

export function canAddStep(draft: FunnelDraft): boolean {
  return draft.steps.length < MAX_STEPS
}

export function canRemoveStep(draft: FunnelDraft): boolean {
  return draft.steps.length > MIN_STEPS
}

/** The matcher value a step actually compares against, given its trigger. */
function matcherValue(step: DraftStep): string {
  return (step.triggerType === 'pageview' ? step.urlPattern : step.eventName).trim()
}

export interface StepError {
  /** Which field is at fault, for inline display. */
  field: 'matcher'
}

export interface DraftValidation {
  valid: boolean
  nameError: 'required' | 'tooLong' | null
  stepsError: 'tooFew' | null
  /** Per-step matcher error keyed by step uid; absent entries are valid. */
  stepErrors: Record<string, StepError>
}

/**
 * Validate a draft against the funnel contract. Returns structured errors (not
 * messages) so the component layer owns i18n. A draft is `valid` only when the
 * name, step count, and every step's matcher are all acceptable.
 */
export function validateDraft(draft: FunnelDraft): DraftValidation {
  const name = draft.name.trim()
  let nameError: DraftValidation['nameError'] = null
  if (name.length === 0) nameError = 'required'
  else if (name.length > MAX_NAME_LENGTH) nameError = 'tooLong'

  const stepsError: DraftValidation['stepsError'] =
    draft.steps.length < MIN_STEPS ? 'tooFew' : null

  const stepErrors: Record<string, StepError> = {}
  for (const step of draft.steps) {
    if (matcherValue(step).length === 0) stepErrors[step.uid] = { field: 'matcher' }
  }

  const valid = !nameError && !stepsError && Object.keys(stepErrors).length === 0
  return { valid, nameError, stepsError, stepErrors }
}

/** Map a draft step to the wire model, dropping the irrelevant matcher field. */
function toFunnelStep(step: DraftStep, index: number): FunnelStep {
  const base: FunnelStep = { step_index: index, trigger_type: step.triggerType }
  const label = step.label.trim()
  if (label) base.label = label.slice(0, MAX_LABEL_LENGTH)
  if (step.triggerType === 'pageview') base.url_pattern = step.urlPattern.trim()
  else base.event_name = step.eventName.trim()
  if (step.filters.length) base.filters = step.filters.map((f) => ({ ...f }))
  return base
}

/**
 * Serialize a *validated* draft to the create/update request body. Steps are
 * re-indexed 0..n to honour the `step_index` contract regardless of any prior
 * reordering. Throws if called on an invalid draft to fail loudly in tests.
 */
export function toCreateRequest(draft: FunnelDraft): FunnelCreateRequest {
  if (!validateDraft(draft).valid) {
    throw new Error('Cannot serialize an invalid funnel draft')
  }
  return {
    name: draft.name.trim(),
    steps: draft.steps.map((step, index) => toFunnelStep(step, index)),
  }
}

/** Move a step up (-1) or down (+1), clamped to the array bounds. */
export function reorderStep(draft: FunnelDraft, index: number, direction: -1 | 1): DraftStep[] {
  const target = index + direction
  if (target < 0 || target >= draft.steps.length) return draft.steps
  const next = [...draft.steps]
  ;[next[index], next[target]] = [next[target], next[index]]
  return next
}
