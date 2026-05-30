// =============================================================================
// Badge / Chip — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Chip from '@/components/ui/badge/Chip.vue'
import { badgeVariants } from '@/components/ui/badge'

describe('badgeVariants', () => {
  it('maps each variant to its token-backed classes', () => {
    expect(badgeVariants({ variant: 'success' })).toContain('text-positive-text')
    expect(badgeVariants({ variant: 'error' })).toContain('text-negative-text')
    expect(badgeVariants({ variant: 'outline' })).toContain('border')
  })
})

describe('Badge', () => {
  it('renders slot content', () => {
    render(Badge, { slots: { default: () => 'LIVE' } })
    expect(screen.getByText('LIVE')).toBeInTheDocument()
  })
})

describe('Chip', () => {
  it('renders its label', () => {
    render(Chip, { props: { label: 'Google' } })
    expect(screen.getByText('Google')).toBeInTheDocument()
  })

  it('emits remove when the remove button is clicked', async () => {
    const { emitted } = render(Chip, { props: { label: 'Google' } })
    await fireEvent.click(screen.getByRole('button', { name: /Remove Google/ }))
    expect(emitted().remove).toBeTruthy()
  })

  it('hides the remove button when not removable', () => {
    render(Chip, { props: { label: 'Static', removable: false } })
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
