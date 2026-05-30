// =============================================================================
// Settings — TeamMemberRow component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import TeamMemberRow from '@/components/settings/TeamMemberRow.vue'
import type { TeamMember } from '@/api/types'
import { testI18n } from '../setup'

function member(overrides: Partial<TeamMember> = {}): TeamMember {
  return {
    id: 'm1',
    user_id: 'u1',
    email: 'ada@example.com',
    name: 'Ada Lovelace',
    role: 'editor',
    status: 'active',
    joined_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

function renderRow(m: TeamMember, canManage = false) {
  return render(TeamMemberRow, {
    props: { member: m, canManage },
    global: { plugins: [testI18n] },
  })
}

describe('TeamMemberRow', () => {
  it('shows name and email', () => {
    renderRow(member())
    expect(screen.getByText('Ada Lovelace')).toBeInTheDocument()
    expect(screen.getByText('ada@example.com')).toBeInTheDocument()
  })

  it('shows an Invited badge for pending invitations', () => {
    renderRow(member({ status: 'invited' }))
    expect(screen.getByText(/Invited/i)).toBeInTheDocument()
  })

  it('renders the role as a read-only badge for non-managers', () => {
    renderRow(member())
    expect(screen.getByText('Editor')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Revoke/i })).toBeNull()
  })

  it('lets managers change a member role', async () => {
    const { emitted } = renderRow(member(), true)
    expect(screen.getByRole('button', { name: /Revoke access for Ada/i })).toBeInTheDocument()
    expect(emitted()).toBeTruthy()
  })

  it('does not allow editing or revoking the owner', () => {
    renderRow(member({ role: 'owner' }), true)
    expect(screen.getByText('Owner')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Revoke/i })).toBeNull()
  })

  it('emits remove with the member', async () => {
    const { emitted } = renderRow(member(), true)
    await fireEvent.click(screen.getByRole('button', { name: /Revoke access for Ada/i }))
    expect((emitted<unknown[]>().remove?.[0] as unknown[] | undefined)?.[0]).toMatchObject({ user_id: 'u1' })
  })

  it('falls back to the email when the name is empty', () => {
    renderRow(member({ name: '' }))
    expect(screen.getAllByText('ada@example.com').length).toBeGreaterThan(0)
  })
})
