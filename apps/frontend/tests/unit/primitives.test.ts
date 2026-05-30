// =============================================================================
// Presentational primitives — Card / Skeleton / Divider / Spinner / Label / ProBadge
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/vue'
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import CardFooter from '@/components/ui/card/CardFooter.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import SkeletonText from '@/components/ui/skeleton/SkeletonText.vue'
import SkeletonCard from '@/components/ui/skeleton/SkeletonCard.vue'
import Divider from '@/components/ui/divider/Divider.vue'
import Spinner from '@/components/ui/spinner/Spinner.vue'
import Label from '@/components/ui/input/Label.vue'
import ProBadge from '@/components/ui/badge/ProBadge.vue'
import { testI18n } from '../setup'

describe('Card', () => {
  it('renders default + header + footer slots', () => {
    render(Card, { slots: { default: () => 'Body' } })
    expect(screen.getByText('Body')).toBeInTheDocument()
    render(CardHeader, { props: { title: 'Top pages' } })
    expect(screen.getByRole('heading', { name: 'Top pages' })).toBeInTheDocument()
    render(CardFooter, { slots: { default: () => 'Footnote' } })
    expect(screen.getByText('Footnote')).toBeInTheDocument()
  })

  it('forwards a custom class', () => {
    const { container } = render(Card, { props: { class: 'my-card' } })
    expect(container.querySelector('.my-card')).toBeInTheDocument()
  })
})

describe('Skeleton family', () => {
  it('renders the shimmer block aria-hidden', () => {
    const { container } = render(Skeleton)
    const el = container.querySelector('.sf-skeleton')
    expect(el?.getAttribute('aria-hidden')).toBe('true')
  })

  it('renders the requested number of text lines', () => {
    const { container } = render(SkeletonText, { props: { lines: 4 } })
    expect(container.querySelectorAll('.sf-skeleton')).toHaveLength(4)
  })

  it('clamps to at least one line', () => {
    const { container } = render(SkeletonText, { props: { lines: 0 } })
    expect(container.querySelectorAll('.sf-skeleton')).toHaveLength(1)
  })

  it('SkeletonCard announces loading to screen readers', () => {
    render(SkeletonCard, { global: { plugins: [testI18n] } })
    expect(screen.getByText('Loading…')).toBeInTheDocument()
  })
})

describe('Divider', () => {
  it('renders a labelled section divider', () => {
    render(Divider, { props: { label: 'or continue with' } })
    expect(screen.getByText('or continue with')).toBeInTheDocument()
  })

  it('renders a plain separator without a label', () => {
    const { container } = render(Divider)
    expect(container.querySelector('[data-orientation]')).toBeTruthy()
  })
})

describe('Spinner', () => {
  it('exposes a status role with an accessible label', () => {
    render(Spinner, { props: { label: 'Fetching' } })
    expect(screen.getByRole('status', { name: 'Fetching' })).toBeInTheDocument()
  })
})

describe('Label', () => {
  it('marks required fields with a hidden "(required)" alternative', () => {
    render(Label, { props: { for: 'email', required: true }, slots: { default: () => 'Email' } })
    expect(screen.getByText('(required)')).toBeInTheDocument()
  })
})

describe('ProBadge', () => {
  it('renders the Pro label', () => {
    render(ProBadge)
    expect(screen.getByText('Pro')).toBeInTheDocument()
  })
})
