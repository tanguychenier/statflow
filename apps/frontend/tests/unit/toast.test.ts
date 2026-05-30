// =============================================================================
// Statflow Dashboard — toast queue tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { useToast } from '@/composables/useToast'

describe('useToast', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    const { toasts, dismiss } = useToast()
    toasts.value.forEach((t) => dismiss(t.id))
  })

  afterEach(() => vi.useRealTimers())

  it('queues a toast and returns its id', () => {
    const { toasts, toast } = useToast()
    const id = toast.success('Saved')
    expect(toasts.value).toHaveLength(1)
    expect(toasts.value[0].id).toBe(id)
    expect(toasts.value[0].variant).toBe('success')
  })

  it('auto-dismisses success after 4s', () => {
    const { toasts, toast } = useToast()
    toast.success('Saved')
    expect(toasts.value).toHaveLength(1)
    vi.advanceTimersByTime(4000)
    expect(toasts.value).toHaveLength(0)
  })

  it('does not auto-dismiss errors', () => {
    const { toasts, toast } = useToast()
    toast.error('Failed')
    vi.advanceTimersByTime(60_000)
    expect(toasts.value).toHaveLength(1)
  })

  it('uses the 6s duration for warnings', () => {
    const { toasts, toast } = useToast()
    toast.warning('Heads up')
    vi.advanceTimersByTime(5999)
    expect(toasts.value).toHaveLength(1)
    vi.advanceTimersByTime(1)
    expect(toasts.value).toHaveLength(0)
  })

  it('honours a custom duration', () => {
    const { toasts, toast } = useToast()
    toast.default('Hi', { duration: 1000 })
    vi.advanceTimersByTime(1000)
    expect(toasts.value).toHaveLength(0)
  })

  it('pauses and resumes the dismiss timer', () => {
    const { toasts, toast, pause, resume } = useToast()
    const id = toast.success('Saved')
    pause(id)
    vi.advanceTimersByTime(10_000)
    expect(toasts.value).toHaveLength(1)
    resume(id)
    vi.advanceTimersByTime(4000)
    expect(toasts.value).toHaveLength(0)
  })

  it('dismisses manually', () => {
    const { toasts, toast, dismiss } = useToast()
    const id = toast.error('Failed')
    dismiss(id)
    expect(toasts.value).toHaveLength(0)
  })

  it('carries an optional action', () => {
    const { toasts, toast } = useToast()
    const onClick = vi.fn()
    toast.default('Undo?', { action: { label: 'Undo', onClick } })
    toasts.value[0].action?.onClick()
    expect(onClick).toHaveBeenCalled()
  })
})
