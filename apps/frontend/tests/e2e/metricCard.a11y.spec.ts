// =============================================================================
// MetricCard — Playwright Accessibility Tests (@axe-core/playwright)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Runs against the Storybook static build (storybook-static/).
// Usage: pnpm build-storybook && pnpm playwright
// =============================================================================

import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const STORYBOOK_URL = process.env.STORYBOOK_URL ?? 'http://localhost:6006'

test.describe('MetricCard accessibility', () => {
  test('Sessions story has no axe violations', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=components-metriccard--sessions&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze()

    expect(results.violations).toEqual([])
  })

  test('Loading story has no axe violations', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=components-metriccard--loading&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze()

    expect(results.violations).toEqual([])
  })

  test('Bounce Rate story has no axe violations', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=components-metriccard--bounce-rate&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze()

    expect(results.violations).toEqual([])
  })

  test('All Variants story has no axe violations', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=components-metriccard--all-variants&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze()

    expect(results.violations).toEqual([])
  })
})
