// =============================================================================
// PageSelectorList — component tests
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Coverage target: ≥90% lines + branches for PageSelectorList.vue
// =============================================================================

import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/vue'
import { createI18n } from 'vue-i18n'
import PageSelectorList from '@/components/behaviour/PageSelectorList.vue'
import en from '@/i18n/locales/en.json'
import { numberFormatsEn } from '@/i18n/numberFormats'
import type { BreakdownRow } from '@/api/types'

const i18n = createI18n({
  legacy: false,
  locale: 'en',
  messages: { en },
  numberFormats: { en: numberFormatsEn },
})

const rows: BreakdownRow[] = [
  { value: '/pricing', metrics: { events: 8203 } },
  { value: '/docs', metrics: { events: 4812 } },
  { value: '/', metrics: { events: 6991 } },
]

function renderList(props: Partial<InstanceType<typeof PageSelectorList>['$props']> = {}) {
  return render(PageSelectorList, {
    props: { rows, selected: '/pricing', ...props },
    global: { plugins: [i18n] },
  })
}

describe('PageSelectorList — rendering', () => {
  it('lists pages ranked by clicks by default', () => {
    renderList()
    const options = screen.getAllByRole('option')
    expect(options[0]).toHaveTextContent('/pricing')
    expect(options[1]).toHaveTextContent('/')
    expect(options[2]).toHaveTextContent('/docs')
  })

  it('marks the selected page as aria-selected', () => {
    renderList({ selected: '/docs' })
    const selected = screen.getByRole('option', { selected: true })
    expect(selected).toHaveTextContent('/docs')
  })
})

describe('PageSelectorList — interaction', () => {
  it('emits select with the clicked pathname', async () => {
    const { emitted } = renderList()
    await fireEvent.click(screen.getByRole('option', { name: /\/docs/ }))
    expect(emitted().select?.[0]).toEqual(['/docs'])
  })

  it('filters rows by the search input', async () => {
    renderList()
    const search = screen.getByRole('searchbox')
    await fireEvent.update(search, 'docs')
    const options = screen.getAllByRole('option')
    expect(options).toHaveLength(1)
    expect(options[0]).toHaveTextContent('/docs')
  })

  it('shows the no-match copy when the search excludes everything', async () => {
    renderList()
    await fireEvent.update(screen.getByRole('searchbox'), 'zzz')
    expect(screen.getByText('No pages match your search.')).toBeInTheDocument()
  })
})

describe('PageSelectorList — loading', () => {
  it('renders busy skeleton rows and no options while loading', () => {
    const { container } = renderList({ loading: true })
    expect(container.querySelector('[aria-busy="true"]')).toBeInTheDocument()
    expect(screen.queryAllByRole('option')).toHaveLength(0)
  })
})
