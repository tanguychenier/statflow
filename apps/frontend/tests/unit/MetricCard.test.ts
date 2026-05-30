// =============================================================================
// MetricCard — Unit tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for MetricCard.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/vue'
import MetricCard from '@/components/MetricCard/MetricCard.vue'
import type { Trend } from '@/types'
import { testI18n } from '../setup'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function renderCard(props: InstanceType<typeof MetricCard>['$props']) {
  return render(MetricCard, { props, global: { plugins: [testI18n] } })
}

const upPositiveTrend: Trend = { value: 12.4, direction: 'up', positiveIsUp: true }
const downPositiveTrend: Trend = { value: 3.2, direction: 'down', positiveIsUp: false }
const downNegativeTrend: Trend = { value: 5.0, direction: 'down', positiveIsUp: true }
const upNegativeTrend: Trend = { value: 2.1, direction: 'up', positiveIsUp: false }
const neutralTrend: Trend = { value: 0, direction: 'neutral', positiveIsUp: true }

// ---------------------------------------------------------------------------
// Loaded state
// ---------------------------------------------------------------------------
describe('MetricCard — loaded state', () => {
  it('renders the title', () => {
    renderCard({ title: 'Sessions', value: '84,203' })
    expect(screen.getByText('Sessions')).toBeInTheDocument()
  })

  it('renders the formatted value', () => {
    renderCard({ title: 'Sessions', value: '84,203' })
    expect(screen.getByText('84,203')).toBeInTheDocument()
  })

  it('renders without a trend when trend prop is omitted', () => {
    renderCard({ title: 'Sessions', value: '84,203' })
    const badge = document.querySelector('.metric-card__trend-badge')
    expect(badge).not.toBeInTheDocument()
  })

  it('renders the sparkline placeholder', () => {
    renderCard({ title: 'Sessions', value: '84,203' })
    const sparkline = document.querySelector('.metric-card__sparkline')
    expect(sparkline).toBeInTheDocument()
  })
})

// ---------------------------------------------------------------------------
// aria-label computation
// ---------------------------------------------------------------------------
describe('MetricCard — aria-label', () => {
  it('constructs aria-label from title and value when no trend', () => {
    const { container } = renderCard({ title: 'Page views', value: '214,880' })
    const article = container.querySelector('article')
    expect(article?.getAttribute('aria-label')).toBe('Page views: 214,880')
  })

  it('includes trend direction in aria-label when trend provided', () => {
    const { container } = renderCard({
      title: 'Sessions',
      value: '84,203',
      trend: upPositiveTrend,
    })
    const article = container.querySelector('article')
    expect(article?.getAttribute('aria-label')).toContain('Up 12.4%')
  })

  it('uses custom description as aria-label when provided', () => {
    const { container } = renderCard({
      title: 'Sessions',
      value: '84,203',
      description: 'Custom accessible description',
    })
    const article = container.querySelector('article')
    expect(article?.getAttribute('aria-label')).toBe('Custom accessible description')
  })

  it('reflects "Down" direction in aria-label for downward trend', () => {
    const { container } = renderCard({
      title: 'Bounce rate',
      value: '42.1%',
      trend: downPositiveTrend,
    })
    const article = container.querySelector('article')
    expect(article?.getAttribute('aria-label')).toContain('Down 3.2%')
  })

  it('reflects "No change" for neutral trend', () => {
    const { container } = renderCard({
      title: 'Sessions',
      value: '0',
      trend: neutralTrend,
    })
    const label = container.querySelector('article')?.getAttribute('aria-label') ?? ''
    expect(label).toContain('No change')
  })
})

// ---------------------------------------------------------------------------
// Trend badge semantics
// ---------------------------------------------------------------------------
describe('MetricCard — trend badge', () => {
  it('applies positive class when direction=up and positiveIsUp=true', () => {
    renderCard({ title: 'Sessions', value: '84,203', trend: upPositiveTrend })
    const badge = document.querySelector('.metric-card__trend-badge--positive')
    expect(badge).toBeInTheDocument()
  })

  it('applies positive class when direction=down and positiveIsUp=false (bounce rate)', () => {
    renderCard({ title: 'Bounce rate', value: '42.1%', trend: downPositiveTrend })
    const badge = document.querySelector('.metric-card__trend-badge--positive')
    expect(badge).toBeInTheDocument()
  })

  it('applies negative class when direction=down and positiveIsUp=true', () => {
    renderCard({ title: 'Sessions', value: '84,203', trend: downNegativeTrend })
    const badge = document.querySelector('.metric-card__trend-badge--negative')
    expect(badge).toBeInTheDocument()
  })

  it('applies negative class when direction=up and positiveIsUp=false', () => {
    renderCard({ title: 'Bounce rate', value: '42.1%', trend: upNegativeTrend })
    const badge = document.querySelector('.metric-card__trend-badge--negative')
    expect(badge).toBeInTheDocument()
  })

  it('applies neutral class for neutral trend', () => {
    renderCard({ title: 'Sessions', value: '0', trend: neutralTrend })
    const badge = document.querySelector('.metric-card__trend-badge--neutral')
    expect(badge).toBeInTheDocument()
  })

  it('renders ▲ arrow for upward trend', () => {
    renderCard({ title: 'Sessions', value: '84,203', trend: upPositiveTrend })
    const arrow = document.querySelector('.metric-card__trend-arrow')
    expect(arrow?.textContent?.trim()).toBe('▲')
  })

  it('renders ▼ arrow for downward trend', () => {
    renderCard({ title: 'Sessions', value: '84,203', trend: downNegativeTrend })
    const arrow = document.querySelector('.metric-card__trend-arrow')
    expect(arrow?.textContent?.trim()).toBe('▼')
  })

  it('renders — for neutral trend', () => {
    renderCard({ title: 'Sessions', value: '0', trend: neutralTrend })
    const arrow = document.querySelector('.metric-card__trend-arrow')
    expect(arrow?.textContent?.trim()).toBe('—')
  })

  it('renders +12.4% label for upward trend', () => {
    renderCard({ title: 'Sessions', value: '84,203', trend: upPositiveTrend })
    const badge = document.querySelector('.metric-card__trend-badge')
    expect(badge?.textContent).toContain('+12.4%')
  })

  it('renders -5.0% label for downward trend', () => {
    renderCard({ title: 'Sessions', value: '84,203', trend: downNegativeTrend })
    const badge = document.querySelector('.metric-card__trend-badge')
    expect(badge?.textContent).toContain('-5.0%')
  })

  it('renders —0.0% for neutral trend', () => {
    renderCard({ title: 'Sessions', value: '0', trend: neutralTrend })
    const badge = document.querySelector('.metric-card__trend-badge')
    expect(badge?.textContent).toContain('0.0%')
  })
})

// ---------------------------------------------------------------------------
// Loading skeleton state
// ---------------------------------------------------------------------------
describe('MetricCard — loading state', () => {
  it('renders skeleton elements when loading=true', () => {
    renderCard({ title: 'Sessions', value: '84,203', loading: true })
    expect(document.querySelector('.metric-card__skeleton--title')).toBeInTheDocument()
    expect(document.querySelector('.metric-card__skeleton--value')).toBeInTheDocument()
    expect(document.querySelector('.metric-card__skeleton--trend')).toBeInTheDocument()
    expect(document.querySelector('.metric-card__skeleton--spark')).toBeInTheDocument()
  })

  it('does not render value text when loading=true', () => {
    renderCard({ title: 'Sessions', value: '84,203', loading: true })
    expect(screen.queryByText('84,203')).not.toBeInTheDocument()
  })

  it('does not render title text when loading=true', () => {
    renderCard({ title: 'Sessions', value: '84,203', loading: true })
    expect(screen.queryByText('Sessions')).not.toBeInTheDocument()
  })

  it('sets aria-busy=true when loading', () => {
    const { container } = renderCard({ title: 'Sessions', value: '0', loading: true })
    const article = container.querySelector('article')
    expect(article?.getAttribute('aria-busy')).toBe('true')
  })

  it('sets aria-busy=false when not loading', () => {
    const { container } = renderCard({ title: 'Sessions', value: '84,203' })
    const article = container.querySelector('article')
    expect(article?.getAttribute('aria-busy')).toBe('false')
  })

  it('renders screen-reader loading text via i18n key', () => {
    renderCard({ title: 'Sessions', value: '0', loading: true })
    // The sr-only span contains the translated "Loading…" string
    expect(screen.getByText('Loading…')).toBeInTheDocument()
  })
})

// ---------------------------------------------------------------------------
// Accessibility — article landmark and ARIA
// ---------------------------------------------------------------------------
describe('MetricCard — accessibility', () => {
  it('renders as an <article> element', () => {
    const { container } = renderCard({ title: 'Sessions', value: '84,203' })
    expect(container.querySelector('article')).toBeInTheDocument()
  })

  it('trend arrow has aria-hidden=true', () => {
    renderCard({ title: 'Sessions', value: '84,203', trend: upPositiveTrend })
    const arrow = document.querySelector('.metric-card__trend-arrow')
    expect(arrow?.getAttribute('aria-hidden')).toBe('true')
  })

  it('sparkline placeholder has aria-hidden=true', () => {
    const { container } = renderCard({ title: 'Sessions', value: '84,203' })
    const sparkline = container.querySelector('.metric-card__sparkline')
    expect(sparkline?.getAttribute('aria-hidden')).toBe('true')
  })

  it('does not put a contradictory role/aria-label on the hidden sparkline', () => {
    const { container } = renderCard({
      title: 'Sessions',
      value: '84,203',
      sparkData: [1, 2, 3],
    })
    const sparkline = container.querySelector('.metric-card__sparkline')
    // aria-hidden removes it from the a11y tree, so role="img"/aria-label here
    // would be dead markup — assert they are absent.
    expect(sparkline?.getAttribute('aria-hidden')).toBe('true')
    expect(sparkline?.hasAttribute('role')).toBe(false)
    expect(sparkline?.hasAttribute('aria-label')).toBe(false)
  })
})
