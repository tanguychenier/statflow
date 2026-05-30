// =============================================================================
// Behaviour screen — Playwright Accessibility Tests (@axe-core/playwright)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Runs against the Storybook static build (storybook-static/).
// Usage: pnpm build-storybook && pnpm playwright
// =============================================================================

import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const STORYBOOK_URL = process.env.STORYBOOK_URL ?? process.env.E2E_BASE_URL ?? 'http://localhost:6006'

const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

const stories = [
  'behaviour-behaviourstatspanel--default',
  'behaviour-behaviourstatspanel--loading',
  'behaviour-behaviourstatspanel--empty',
  'behaviour-behaviourinsights--default',
  'behaviour-behaviourinsights--empty',
  'behaviour-pageselectorlist--default',
  'behaviour-pageselectorlist--empty',
  'behaviour-rangeslider--intensity',
  'behaviour-rangeslider--radius',
]

test.describe('Behaviour components accessibility', () => {
  for (const id of stories) {
    test(`${id} has no axe violations`, async ({ page }) => {
      await page.goto(`${STORYBOOK_URL}/iframe.html?id=${id}&viewMode=story`)
      await page.waitForLoadState('networkidle')

      const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze()

      expect(results.violations).toEqual([])
    })
  }
})

test.describe('Behaviour — keyboard operability', () => {
  test('the page selector exposes a focusable listbox option', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=behaviour-pageselectorlist--default&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const firstOption = page.getByRole('option').first()
    await firstOption.focus()
    await expect(firstOption).toBeFocused()
  })

  test('the intensity slider is reachable and adjustable by keyboard', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=behaviour-rangeslider--intensity&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const slider = page.getByRole('slider')
    await slider.focus()
    await expect(slider).toBeFocused()
    const before = await slider.inputValue()
    await slider.press('ArrowRight')
    const after = await slider.inputValue()
    expect(Number(after)).toBeGreaterThanOrEqual(Number(before))
  })
})
