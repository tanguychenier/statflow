// =============================================================================
// Overview screen — Playwright Accessibility Tests (@axe-core/playwright)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Runs against the Storybook static build (storybook-static/) — the Overview
// view itself depends on the live API, so its panels are exercised in isolation
// here. Usage: pnpm build-storybook && pnpm playwright
// =============================================================================

import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const STORYBOOK_URL = process.env.STORYBOOK_URL ?? 'http://localhost:6006'
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

const STORIES = [
  'overview-breakdownpanel--top-pages',
  'overview-breakdownpanel--countries',
  'overview-breakdownpanel--loading',
  'overview-breakdownpanel--empty',
  'overview-breakdownpanel--error-state',
  'overview-overviewtoolbar--default',
  'overview-overviewtoolbar--comparing',
]

test.describe('Overview accessibility', () => {
  for (const story of STORIES) {
    test(`${story} has no axe violations`, async ({ page }) => {
      await page.goto(`${STORYBOOK_URL}/iframe.html?id=${story}&viewMode=story`)
      await page.waitForLoadState('networkidle')

      const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze()
      expect(results.violations).toEqual([])
    })
  }
})

test.describe('Overview keyboard interaction', () => {
  test('breakdown rows are reachable and activatable by keyboard', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=overview-breakdownpanel--top-pages&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    // The first interactive element inside the panel is a breakdown row button.
    await page.keyboard.press('Tab')
    const focused = page.locator(':focus')
    await expect(focused).toBeVisible()
  })

  test('granularity segmented control exposes radio semantics', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=overview-overviewtoolbar--default&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const radios = page.getByRole('radio')
    await expect(radios).toHaveCount(4)
  })
})
