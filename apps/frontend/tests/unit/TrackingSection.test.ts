// =============================================================================
// Settings — TrackingSection component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import TrackingSection from '@/components/settings/TrackingSection.vue'
import type { SiteSettings } from '@/api/types'
import { testI18n } from '../setup'

const settings: SiteSettings = {
  excluded_ips: ['10.0.0.1'],
  allowed_domains: ['example.com'],
  data_retention_days: 90,
  strip_query_params: false,
  tracker_config: { track_clicks: true },
}

function renderSection(props: Record<string, unknown> = {}) {
  return render(TrackingSection, {
    props: { settings, ...props },
    global: { plugins: [testI18n] },
  })
}

describe('TrackingSection', () => {
  it('renders the locked cookieless note', () => {
    renderSection()
    expect(screen.getByText(/cookieless by design/i)).toBeInTheDocument()
  })

  it('shows existing IPs and domains as chips', () => {
    renderSection()
    expect(screen.getByText('10.0.0.1')).toBeInTheDocument()
    expect(screen.getByText('example.com')).toBeInTheDocument()
  })

  it('emits a full settings object on save', async () => {
    const { emitted } = renderSection()
    await fireEvent.click(screen.getByRole('button', { name: /Save changes/i }))
    const payload = (emitted<unknown[]>().save?.[0] as SiteSettings[] | undefined)?.[0] as SiteSettings
    expect(payload.excluded_ips).toEqual(['10.0.0.1'])
    expect(payload.allowed_domains).toEqual(['example.com'])
    expect(payload.data_retention_days).toBe(90)
  })

  it('toggles a collection switch into the saved payload', async () => {
    const { emitted } = renderSection()
    await fireEvent.click(screen.getByRole('switch', { name: /Track clicks/i }))
    await fireEvent.click(screen.getByRole('button', { name: /Save changes/i }))
    const payload = (emitted<unknown[]>().save?.[0] as SiteSettings[] | undefined)?.[0] as SiteSettings
    expect(payload.tracker_config?.track_clicks).toBe(false)
  })

  it('shows the custom retention input only for non-preset values', () => {
    // 90 is a preset → no custom field
    renderSection()
    expect(screen.queryByLabelText(/Custom retention/i)).toBeNull()
  })

  it('reveals a custom retention field for a non-preset value', () => {
    renderSection({ settings: { ...settings, data_retention_days: 123 } })
    expect(screen.getByLabelText(/Custom retention/i)).toBeInTheDocument()
  })

  it('keeps the custom number within the saved retention bounds', async () => {
    const { emitted } = renderSection({ settings: { ...settings, data_retention_days: 200 } })
    const input = screen.getByLabelText(/Custom retention/i)
    await fireEvent.update(input, '500')
    await fireEvent.click(screen.getByRole('button', { name: /Save changes/i }))
    const payload = (emitted<unknown[]>().save?.[0] as SiteSettings[] | undefined)?.[0] as SiteSettings
    expect(payload.data_retention_days).toBe(500)
  })

  it('disables save for an out-of-range custom retention', async () => {
    renderSection({ settings: { ...settings, data_retention_days: 200 } })
    await fireEvent.update(screen.getByLabelText(/Custom retention/i), '5')
    expect(screen.getByRole('button', { name: /Save changes/i })).toHaveAttribute(
      'aria-disabled',
      'true',
    )
  })

  it('disables saving when the IP list is invalid', () => {
    renderSection({ settings: { ...settings, excluded_ips: ['bad-ip'] } })
    expect(screen.getByRole('button', { name: /Save changes/i })).toHaveAttribute(
      'aria-disabled',
      'true',
    )
  })

  it('hides the save action when readonly', () => {
    renderSection({ readonly: true })
    expect(screen.queryByRole('button', { name: /Save changes/i })).toBeNull()
  })
})
