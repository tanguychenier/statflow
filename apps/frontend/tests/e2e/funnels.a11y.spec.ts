// =============================================================================
// Funnels — Playwright accessibility tests (@axe-core/playwright)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Runs against the Storybook static build (storybook-static/).
// Usage: pnpm build-storybook && pnpm playwright
// =============================================================================

import { test, expect, type Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const STORYBOOK_URL = process.env.STORYBOOK_URL ?? 'http://localhost:6006'
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

async function expectNoViolations(page: Page, storyId: string) {
  await page.goto(`${STORYBOOK_URL}/iframe.html?id=${storyId}&viewMode=story`)
  await page.waitForLoadState('networkidle')
  const results = await new AxeBuilder({ page }).withTags(WCAG).analyze()
  expect(results.violations).toEqual([])
}

test.describe('Funnels accessibility', () => {
  test('FunnelListItem (queried) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'funnels-funnellistitem--queried')
  })

  test('FunnelListItem (not yet queried) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'funnels-funnellistitem--not-yet-queried')
  })

  test('FunnelSummaryStats default has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'funnels-funnelsummarystats--default')
  })

  test('FunnelSummaryStats loading has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'funnels-funnelsummarystats--loading')
  })

  test('FunnelStepRow (pageview) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'funnels-funnelsteprow--pageview')
  })

  test('FunnelStepRow (event) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'funnels-funnelsteprow--event')
  })

  test('FunnelBuilderModal (create) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'funnels-funnelbuildermodal--create')
  })

  test('FunnelBuilderModal (edit) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'funnels-funnelbuildermodal--edit')
  })
})
