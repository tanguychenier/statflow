// =============================================================================
// Toaster — component tests (renders the global toast queue)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import { nextTick } from 'vue'
import Toaster from '@/components/ui/toast/Toaster.vue'
import { useToast } from '@/composables/useToast'
import { testI18n } from '../setup'

describe('Toaster', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    const { toasts, dismiss } = useToast()
    ;[...toasts.value].forEach((t) => dismiss(t.id))
  })
  afterEach(() => vi.useRealTimers())

  it('renders a polite live region', () => {
    render(Toaster, { global: { plugins: [testI18n] } })
    const region = screen.getByRole('region', { name: 'Notifications' })
    expect(region.getAttribute('aria-live')).toBe('polite')
  })

  it('renders a queued toast and dismisses it on click', async () => {
    render(Toaster, { global: { plugins: [testI18n] } })
    const { toast } = useToast()
    toast.error('Failed to save')
    await nextTick()
    expect(screen.getByText('Failed to save')).toBeInTheDocument()
    await fireEvent.click(screen.getByRole('button', { name: 'Dismiss notification' }))
    await nextTick()
    expect(screen.queryByText('Failed to save')).not.toBeInTheDocument()
  })

  it('renders the action button and invokes its handler', async () => {
    render(Toaster, { global: { plugins: [testI18n] } })
    const onClick = vi.fn()
    const { toast } = useToast()
    toast.default('Deleted', { action: { label: 'Undo', onClick } })
    await nextTick()
    await fireEvent.click(screen.getByRole('button', { name: 'Undo' }))
    expect(onClick).toHaveBeenCalled()
  })
})
