// =============================================================================
// Design system — Playwright accessibility specs (@axe-core/playwright)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Runs against the Storybook static build. Each story is checked for WCAG 2.x
// A/AA violations. Usage: pnpm build-storybook && pnpm playwright
// =============================================================================

import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const STORYBOOK_URL = process.env.STORYBOOK_URL ?? 'http://localhost:6006'

const STORIES = [
  'ui-button--all-variants',
  'ui-button--loading',
  'ui-badge--variants',
  'ui-emptystate--no-data',
  'ui-emptystate--error-state',
  'charts-timeserieschart--single-series',
  'charts-timeserieschart--empty',
]

test.describe('design system accessibility', () => {
  for (const storyId of STORIES) {
    test(`${storyId} has no axe violations`, async ({ page }) => {
      await page.goto(`${STORYBOOK_URL}/iframe.html?id=${storyId}&viewMode=story`)
      await page.waitForLoadState('networkidle')

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze()

      expect(results.violations).toEqual([])
    })
  }
})
