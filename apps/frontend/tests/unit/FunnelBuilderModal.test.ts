// =============================================================================
// FunnelBuilderModal — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The design-system Modal teleports its content through a Reka DialogPortal,
// which is brittle under jsdom, so it is stubbed with an inline pass-through that
// renders the default + footer slots. The builder's own logic (validation,
// step add/remove, serialization on save) is what these tests exercise.
// =============================================================================

import { describe, it, expect } from 'vitest'
import { defineComponent, h } from 'vue'
import { render, screen, fireEvent } from '@testing-library/vue'
import FunnelBuilderModal from '@/components/funnels/FunnelBuilderModal.vue'
import type { Funnel, FunnelCreateRequest } from '@/api/types'
import { testI18n } from '../setup'

// Inline pass-through Modal so default + footer slots render in the DOM tree.
const ModalStub = defineComponent({
  props: { open: Boolean, title: String },
  setup: (props, { slots }) =>
    () =>
      props.open
        ? h('div', { role: 'dialog', 'aria-label': props.title }, [
            slots.default?.(),
            slots.footer?.(),
          ])
        : null,
})

function renderModal(props: Record<string, unknown> = {}) {
  return render(FunnelBuilderModal, {
    props: { open: true, ...props },
    global: {
      plugins: [testI18n],
      stubs: { Modal: ModalStub },
    },
  })
}

const funnel: Funnel = {
  id: 'f1',
  site_id: 's1',
  name: 'Signup',
  steps: [
    { step_index: 0, label: 'Home', trigger_type: 'pageview', url_pattern: '/' },
    { step_index: 1, label: 'Done', trigger_type: 'event', event_name: 'signup' },
  ],
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-02T00:00:00Z',
}

describe('FunnelBuilderModal', () => {
  it('renders two empty steps for a new funnel', () => {
    renderModal()
    expect(screen.getAllByRole('listitem')).toHaveLength(2)
  })

  it('adds a step', async () => {
    renderModal()
    await fireEvent.click(screen.getByRole('button', { name: 'Add step' }))
    expect(screen.getAllByRole('listitem')).toHaveLength(3)
  })

  it('caps the step count at sixteen', async () => {
    renderModal()
    const add = screen.getByRole('button', { name: 'Add step' })
    for (let i = 0; i < 20; i += 1) {
      if (add.getAttribute('aria-disabled') !== 'true') await fireEvent.click(add)
    }
    expect(screen.getAllByRole('listitem')).toHaveLength(16)
    expect(add).toHaveAttribute('aria-disabled', 'true')
  })

  it('blocks save and surfaces errors for an invalid draft', async () => {
    const { emitted } = renderModal()
    await fireEvent.click(screen.getByRole('button', { name: 'Save funnel' }))
    expect(emitted().save).toBeFalsy()
    expect(screen.getByText('A funnel name is required.')).toBeInTheDocument()
  })

  it('emits a serialized request on a valid save', async () => {
    const { emitted } = renderModal()
    await fireEvent.update(
      screen.getByLabelText('Funnel name', { exact: false }),
      'Checkout',
    )
    const matchers = screen.getAllByLabelText(/Match value for step/)
    await fireEvent.update(matchers[0], '/product')
    await fireEvent.update(matchers[1], '/cart')
    await fireEvent.click(screen.getByRole('button', { name: 'Save funnel' }))

    const body = (emitted<unknown[]>().save?.[0] as FunnelCreateRequest[] | undefined)?.[0] as FunnelCreateRequest
    expect(body.name).toBe('Checkout')
    expect(body.steps).toHaveLength(2)
    expect(body.steps[0].url_pattern).toBe('/product')
  })

  it('pre-fills from a funnel in edit mode', () => {
    renderModal({ funnel })
    expect(screen.getByDisplayValue('Signup')).toBeInTheDocument()
    expect(screen.getByDisplayValue('/')).toBeInTheDocument()
    expect(screen.getByDisplayValue('signup')).toBeInTheDocument()
  })

  it('cancels by requesting close', async () => {
    const { emitted } = renderModal()
    await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    expect((emitted<unknown[]>()['update:open']?.[0] as unknown[] | undefined)?.[0]).toBe(false)
  })

  it('shows the dialog title for create vs edit', () => {
    const create = renderModal()
    expect(screen.getByRole('dialog', { name: 'New funnel' })).toBeInTheDocument()
    create.unmount()

    renderModal({ funnel })
    expect(screen.getByRole('dialog', { name: 'Edit funnel' })).toBeInTheDocument()
  })
})
