// =============================================================================
// Button — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import { h } from 'vue'
import Button from '@/components/ui/button/Button.vue'
import { buttonVariants } from '@/components/ui/button'

describe('buttonVariants', () => {
  it('produces the default variant + size classes', () => {
    const classes = buttonVariants()
    expect(classes).toContain('bg-accent')
    expect(classes).toContain('h-9')
  })

  it('switches classes by variant and size', () => {
    expect(buttonVariants({ variant: 'destructive' })).toContain('bg-negative')
    expect(buttonVariants({ variant: 'ghost' })).toContain('bg-transparent')
    expect(buttonVariants({ size: 'icon' })).toContain('w-9')
  })
})

describe('Button', () => {
  it('renders slot content', () => {
    render(Button, { slots: { default: () => 'Save' } })
    expect(screen.getByText('Save')).toBeInTheDocument()
  })

  it('merges a custom class through to the root', () => {
    const { container } = render(Button, {
      props: { class: 'custom-x' },
      slots: { default: () => 'Go' },
    })
    expect(container.querySelector('.custom-x')).toBeInTheDocument()
  })

  it('marks a disabled button with aria-disabled + tabindex=-1, not the native attribute', () => {
    render(Button, { props: { disabled: true }, slots: { default: () => 'X' } })
    const button = screen.getByRole('button')
    // accessibility.md §3.3: keep it discoverable by screen readers (no native
    // `disabled`), out of the tab order, and announced as disabled.
    expect(button.getAttribute('aria-disabled')).toBe('true')
    expect(button.getAttribute('tabindex')).toBe('-1')
    expect(button.hasAttribute('disabled')).toBe(false)
  })

  it('does not have aria-disabled or a forced tabindex when enabled', () => {
    render(Button, { slots: { default: () => 'X' } })
    const button = screen.getByRole('button')
    expect(button.hasAttribute('aria-disabled')).toBe(false)
    expect(button.hasAttribute('tabindex')).toBe(false)
  })

  it('swallows clicks while disabled', async () => {
    const onClick = vi.fn()
    render(Button, {
      props: { disabled: true },
      attrs: { onClick },
      slots: { default: () => 'X' },
    })
    await fireEvent.click(screen.getByRole('button'))
    expect(onClick).not.toHaveBeenCalled()
  })

  it('shows a spinner and is busy/disabled while loading', () => {
    render(Button, { props: { loading: true }, slots: { default: () => 'X' } })
    const button = screen.getByRole('button')
    expect(button.getAttribute('aria-busy')).toBe('true')
    expect(button.getAttribute('aria-disabled')).toBe('true')
    expect(button.getAttribute('tabindex')).toBe('-1')
  })

  it('renders as a custom element via the `as` prop', () => {
    const { container } = render(Button, {
      props: { as: 'a' },
      attrs: { href: '#x' },
      slots: { default: () => 'Link' },
    })
    expect(container.querySelector('a')).toBeInTheDocument()
  })

  it('renders leading slot content when not loading', () => {
    render(Button, {
      slots: { leading: () => h('span', { 'data-testid': 'lead' }, '◆'), default: () => 'X' },
    })
    expect(screen.getByTestId('lead')).toBeInTheDocument()
  })
})
