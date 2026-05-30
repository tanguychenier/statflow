// =============================================================================
// Settings — TeamSection component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import TeamSection from '@/components/settings/TeamSection.vue'
import type { TeamMember } from '@/api/types'
import { testI18n } from '../setup'

const members: TeamMember[] = [
  {
    id: 'm1',
    user_id: 'u1',
    email: 'owner@example.com',
    name: 'Grace Hopper',
    role: 'owner',
    status: 'active',
    joined_at: '2026-01-01T00:00:00Z',
  },
  {
    id: 'm2',
    user_id: 'u2',
    email: 'ada@example.com',
    name: 'Ada',
    role: 'editor',
    status: 'active',
    joined_at: '2026-02-01T00:00:00Z',
  },
]

function renderSection(props: Record<string, unknown> = {}) {
  return render(TeamSection, {
    props: { members, ...props },
    global: { plugins: [testI18n] },
  })
}

describe('TeamSection', () => {
  it('lists members', () => {
    renderSection()
    expect(screen.getByText('Grace Hopper')).toBeInTheDocument()
    expect(screen.getByText('Ada')).toBeInTheDocument()
  })

  it('shows the four-role legend', () => {
    renderSection()
    expect(screen.getByText(/Role permissions/i)).toBeInTheDocument()
    expect(screen.getByText(/Full control/i)).toBeInTheDocument()
    expect(screen.getByText(/read-only access/i)).toBeInTheDocument()
  })

  it('hides the invite form for non-managers', () => {
    renderSection({ canManage: false })
    expect(screen.queryByRole('button', { name: /Send invite/i })).toBeNull()
  })

  it('shows the invite form and forwards invites for managers', async () => {
    const { emitted } = renderSection({ canManage: true })
    await fireEvent.update(screen.getByLabelText(/Email address/i), 'new@example.com')
    await fireEvent.click(screen.getByRole('button', { name: /Send invite/i }))
    expect(emitted().invite?.[0]).toEqual([{ email: 'new@example.com', role: 'viewer' }])
  })

  it('renders a loading skeleton', () => {
    const { container } = renderSection({ loading: true })
    expect(container.querySelector('[aria-busy="true"]')).not.toBeNull()
  })

  it('renders an error state with a retry action', async () => {
    const { emitted } = renderSection({ error: true })
    await fireEvent.click(screen.getByRole('button', { name: /Retry/i }))
    expect(emitted().retry).toBeTruthy()
  })

  it('renders an empty state when there are no members', () => {
    renderSection({ members: [] })
    expect(screen.getByText(/No team members yet/i)).toBeInTheDocument()
  })

  it('forwards member removal', async () => {
    const { emitted } = renderSection({ canManage: true })
    await fireEvent.click(screen.getByRole('button', { name: /Revoke access for Ada/i }))
    expect((emitted<unknown[]>().remove?.[0] as unknown[] | undefined)?.[0]).toMatchObject({ user_id: 'u2' })
  })
})
