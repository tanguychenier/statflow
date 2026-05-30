// =============================================================================
// EmptyState — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import EmptyState from '@/components/ui/empty-state/EmptyState.vue'
import { testI18n } from '../setup'

function renderState(props: InstanceType<typeof EmptyState>['$props']) {
  return render(EmptyState, { props, global: { plugins: [testI18n] } })
}

describe('EmptyState', () => {
  it('renders title and description', () => {
    renderState({ title: 'No data', description: 'Try another range' })
    expect(screen.getByText('No data')).toBeInTheDocument()
    expect(screen.getByText('Try another range')).toBeInTheDocument()
  })

  it('renders the action button and emits action on click', async () => {
    const { emitted } = renderState({ title: 'Empty', actionLabel: 'Adjust filters' })
    const button = screen.getByRole('button', { name: 'Adjust filters' })
    await fireEvent.click(button)
    expect(emitted().action).toBeTruthy()
  })

  it('omits the action button when no label is given', () => {
    renderState({ title: 'Empty' })
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('renders an aria-hidden illustration per variant', () => {
    const { container } = renderState({ title: 'Error', variant: 'error' })
    const svg = container.querySelector('svg')
    expect(svg?.getAttribute('aria-hidden')).toBe('true')
  })

  it('exposes role=status for screen readers', () => {
    renderState({ title: 'Empty' })
    expect(screen.getByRole('status')).toBeInTheDocument()
  })
})
