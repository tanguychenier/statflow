// =============================================================================
// Settings — Playwright accessibility tests (@axe-core/playwright)
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

test.describe('Settings accessibility', () => {
  test('GeneralSection has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'settings-generalsection--default')
  })

  test('GeneralSection (read-only) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'settings-generalsection--read-only')
  })

  test('TrackerSnippetCard has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'settings-trackersnippetcard--default')
  })

  test('TrackerSnippetCard (after rotation) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'settings-trackersnippetcard--after-rotation')
  })

  test('TrackingSection has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'settings-trackingsection--default')
  })

  test('TrackingSection (custom retention) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'settings-trackingsection--custom-retention')
  })

  test('TeamSection (manager) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'settings-teamsection--manager')
  })

  test('TeamSection (empty) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'settings-teamsection--empty')
  })

  test('TeamSection (error) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'settings-teamsection--error-state')
  })

  test('DangerZone (owner) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'settings-dangerzone--owner')
  })

  test('ListEditor (IPs) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'settings-listeditor--ips')
  })

  test('ListEditor (invalid entry) has no axe violations', async ({ page }) => {
    await expectNoViolations(page, 'settings-listeditor--with-invalid-entry')
  })
})
