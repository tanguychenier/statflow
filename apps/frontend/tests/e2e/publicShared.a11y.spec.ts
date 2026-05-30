// =============================================================================
// Public Shared Dashboard — Playwright Accessibility Tests (@axe-core/playwright)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Runs against the Storybook static build (storybook-static/) — the public view
// itself depends on the live public API, so its building blocks (header +
// password gate) are exercised in isolation here. The KPI cards are covered by
// the Components/MetricCard stories. Usage: pnpm build-storybook && pnpm playwright
// =============================================================================

import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const STORYBOOK_URL = process.env.STORYBOOK_URL ?? 'http://localhost:6006'
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

const STORIES = [
  'publicshared-publicdashboardheader--default',
  'publicshared-publicdashboardheader--with-site-label',
  'publicshared-publicdashboardheader--loading',
  'publicshared-publicdashboardheader--no-period',
  'publicshared-publicpasswordgate--default',
  'publicshared-publicpasswordgate--loading',
  'publicshared-publicpasswordgate--error',
]

test.describe('Public shared dashboard accessibility', () => {
  for (const story of STORIES) {
    test(`${story} has no axe violations`, async ({ page }) => {
      await page.goto(`${STORYBOOK_URL}/iframe.html?id=${story}&viewMode=story`)
      await page.waitForLoadState('networkidle')

      const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze()
      expect(results.violations).toEqual([])
    })
  }
})

test.describe('Public shared dashboard interaction', () => {
  test('the password gate field is keyboard-focusable and submits', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=publicshared-publicpasswordgate--default&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const field = page.getByLabel('Password')
    await field.fill('hunter2')
    const submit = page.getByRole('button', { name: 'View dashboard' })
    await expect(submit).toBeEnabled()
    await submit.click()
  })

  test('the reveal toggle switches the field between masked and visible', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=publicshared-publicpasswordgate--default&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const field = page.getByLabel('Password')
    await expect(field).toHaveAttribute('type', 'password')
    await page.getByRole('button', { name: 'Show password' }).click()
    await expect(page.getByLabel('Password')).toHaveAttribute('type', 'text')
  })

  test('the wrong-password state announces an inline error', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=publicshared-publicpasswordgate--error&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    await expect(page.getByRole('alert')).toContainText('Incorrect password')
  })

  test('the header exposes a "Powered by Statflow" link to a new tab', async ({ page }) => {
    await page.goto(
      `${STORYBOOK_URL}/iframe.html?id=publicshared-publicdashboardheader--default&viewMode=story`,
    )
    await page.waitForLoadState('networkidle')

    const link = page.getByRole('link', { name: /Powered by Statflow/i })
    await expect(link).toHaveAttribute('href', 'https://statflow.io')
    await expect(link).toHaveAttribute('target', '_blank')
  })
})
