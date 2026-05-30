// =============================================================================
// Avatar — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/vue'
import Avatar from '@/components/ui/avatar/Avatar.vue'

describe('Avatar', () => {
  it('derives up to two initials from a name', () => {
    render(Avatar, { props: { name: 'Tanguy Chénier' } })
    expect(screen.getByText('TC')).toBeInTheDocument()
  })

  it('uses a single initial for a one-word name', () => {
    render(Avatar, { props: { name: 'Statflow' } })
    expect(screen.getByText('S')).toBeInTheDocument()
  })

  it('falls back to the user icon when no name is given', () => {
    const { container } = render(Avatar, {})
    // No initials text node; the fallback icon (an svg) is rendered instead.
    expect(container.querySelector('svg')).toBeInTheDocument()
  })
})
