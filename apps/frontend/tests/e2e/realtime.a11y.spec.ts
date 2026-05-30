// =============================================================================
// Realtime screen — Playwright Accessibility Tests (@axe-core/playwright)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Runs against the Storybook static build (storybook-static/) — the Realtime
// view itself depends on a live SSE stream, so its panels are exercised in
// isolation here. Usage: pnpm build-storybook && pnpm playwright
// =============================================================================

import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const STORYBOOK_URL = process.env.STORYBOOK_URL ?? 'http://localhost:6006'
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

const STORIES = [
  'realtime-realtimekpicard--active-users',
  'realtime-realtimekpicard--top-page',
  'realtime-realtimekpicard--loading',
  'realtime-realtimebreakdownlist--top-pages',
  'realtime-realtimebreakdownlist--countries',
  'realtime-realtimebreakdownlist--loading',
  'realtime-realtimebreakdownlist--empty',
  'realtime-realtimebreakdownlist--error-state',
  'realtime-liveeventstream--streaming',
  'realtime-liveeventstream--paused',
  'realtime-liveeventstream--connecting',
  'realtime-liveeventstream--empty',
  'realtime-realtimetrendchart--trend',
  'realtime-realtimetrendchart--empty',
]

test.describe('Realtime accessibility', () => {
  for (const story of STORIES) {
    test(`${story} has no axe violations`, async ({ page }) => {
      await page.goto(`${STORYBOOK_URL}/iframe.html?id=${story}&viewMode=story`)
      await page.waitForLoadState('networkidle')

      const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze()
      expect(results.violations).toEqual([])
    })
  }
})

test.describe('Realtime keyboard & live-region semantics', () => {
  test('the pause control is keyboard-operable and toggles its label', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=realtime-liveeventstream--streaming&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const pause = page.getByRole('button', { name: /Pause/i })
    await expect(pause).toBeVisible()
    await pause.focus()
    await expect(page.locator(':focus')).toBeVisible()
  })

  test('the event log exposes a polite live region', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=realtime-liveeventstream--streaming&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const log = page.getByRole('log')
    await expect(log).toHaveAttribute('aria-live', 'polite')
  })

  test('KPI cards expose an accessible label combining metric and value', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=realtime-realtimekpicard--active-users&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    await expect(page.getByLabel(/Active users: 247/)).toBeVisible()
  })

  test('the active-user count is announced through a polite, atomic live region', async ({
    page,
  }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=realtime-realtimekpicard--active-users&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const liveRegion = page.locator('[aria-live="polite"][aria-atomic="true"]')
    await expect(liveRegion).toHaveCount(1)
    await expect(liveRegion).toContainText('Active users: 247')
  })
})
