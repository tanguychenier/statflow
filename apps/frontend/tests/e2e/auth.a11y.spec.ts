// =============================================================================
// Auth screens — Playwright Accessibility Tests (@axe-core/playwright)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Runs against the Storybook static build (storybook-static/). The auth views
// depend on the live API for submission, but their rendered forms are static
// and exercised here for WCAG 2.2 AA conformance and keyboard reachability.
// Usage: pnpm build-storybook && pnpm playwright
// =============================================================================

import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const STORYBOOK_URL = process.env.STORYBOOK_URL ?? 'http://localhost:6006'
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

const STORIES = [
  'auth-screens-login--default',
  'auth-screens-login--french',
  'auth-screens-register--default',
  'auth-screens-forgotpassword--default',
  'auth-screens-forgotpassword--submitted',
  'auth-screens-resetpassword--with-token',
  'auth-screens-resetpassword--missing-token',
  'auth-authcard--default',
  'auth-passwordinput--empty',
  'auth-passwordinput--error',
]

test.describe('Auth accessibility', () => {
  for (const story of STORIES) {
    test(`${story} has no axe violations`, async ({ page }) => {
      await page.goto(`${STORYBOOK_URL}/iframe.html?id=${story}&viewMode=story`)
      await page.waitForLoadState('networkidle')

      const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze()
      expect(results.violations).toEqual([])
    })
  }
})

test.describe('Auth keyboard interaction', () => {
  test('login fields and primary action are reachable by keyboard', async ({ page }) => {
    await page.goto(`${STORYBOOK_URL}/iframe.html?id=auth-screens-login--default&viewMode=story`)
    await page.waitForLoadState('networkidle')

    await page.getByLabel('Email').focus()
    await expect(page.getByLabel('Email')).toBeFocused()

    await page.keyboard.type('ada@example.com')
    await page.keyboard.press('Tab') // → password field
    await page.keyboard.type('a-valid-password')
    await page.keyboard.press('Tab') // → show/hide toggle
    await expect(page.getByRole('button', { name: 'Show password' })).toBeFocused()
  })

  test('the password reveal toggle exposes its pressed state', async ({ page }) => {
    await page.goto(`${STORYBOOK_URL}/iframe.html?id=auth-passwordinput--filled&viewMode=story`)
    await page.waitForLoadState('networkidle')

    const toggle = page.getByRole('button', { name: 'Show password' })
    await expect(toggle).toHaveAttribute('aria-pressed', 'false')
    await toggle.click()
    await expect(page.getByRole('button', { name: 'Hide password' })).toHaveAttribute(
      'aria-pressed',
      'true',
    )
  })
})
