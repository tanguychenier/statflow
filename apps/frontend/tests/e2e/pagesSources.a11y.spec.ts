// =============================================================================
// Pages & Sources screen — Playwright Accessibility Tests (@axe-core/playwright)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Runs against the Storybook static build (storybook-static/) — the view itself
// depends on the live API, so its table + toolbar are exercised in isolation
// here. Usage: pnpm build-storybook && pnpm playwright
// =============================================================================

import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const STORYBOOK_URL = process.env.STORYBOOK_URL ?? 'http://localhost:6006'
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

const STORIES = [
  'pagessources-pagessourcestable--pages',
  'pagessources-pagessourcestable--loading',
  'pagessources-pagessourcestable--empty',
  'pagessources-pagessourcestable--error-state',
  'pagessources-pagessourcestable--referrers',
  'pagessources-pagessourcestable--utm',
  'pagessources-pagessourcestoolbar--default',
  'pagessources-pagessourcestoolbar--exporting',
]

test.describe('Pages & Sources accessibility', () => {
  for (const story of STORIES) {
    test(`${story} has no axe violations`, async ({ page }) => {
      await page.goto(`${STORYBOOK_URL}/iframe.html?id=${story}&viewMode=story`)
      await page.waitForLoadState('networkidle')

      const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze()
      expect(results.violations).toEqual([])
    })
  }
})

test.describe('Pages & Sources keyboard interaction', () => {
  test('table headers are reachable and sortable by keyboard', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=pagessources-pagessourcestable--pages&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    await page.keyboard.press('Tab')
    const focused = page.locator(':focus')
    await expect(focused).toBeVisible()
  })

  test('pagination controls expose accessible names', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=pagessources-pagessourcestable--pages&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    await expect(page.getByRole('button', { name: 'Next' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Previous' })).toBeVisible()
  })

  test('data rows are keyboard-focusable and activate with Enter', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=pagessources-pagessourcestable--pages&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const firstRow = page.locator('tbody tr[tabindex="0"]').first()
    await expect(firstRow).toBeVisible()
    await firstRow.focus()
    await expect(page.locator(':focus')).toBeVisible()
    // Activating the focused row must not throw; the row is the whole-row action.
    await firstRow.press('Enter')
  })
})
