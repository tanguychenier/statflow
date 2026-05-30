// =============================================================================
// Funnels — builder interaction tests (Playwright against Storybook)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Drives the builder UI in a real browser to confirm the add/remove/reorder
// affordances and step-limit guard behave as designed. Runs against the static
// Storybook build (storybook-static/).
// =============================================================================

import { test, expect } from '@playwright/test'

const STORYBOOK_URL = process.env.STORYBOOK_URL ?? 'http://localhost:6006'

test.beforeEach(async ({ page }) => {
  await page.goto(`${STORYBOOK_URL}/iframe.html?id=funnels-funnelbuildermodal--create&viewMode=story`)
  await page.waitForLoadState('networkidle')
})

test.describe('Funnel builder', () => {
  test('starts with two steps', async ({ page }) => {
    await expect(page.getByRole('listitem')).toHaveCount(2)
  })

  test('adds steps up to the sixteen-step limit', async ({ page }) => {
    const add = page.getByRole('button', { name: 'Add step' })
    for (let i = 0; i < 20; i += 1) {
      if (await add.isEnabled()) await add.click()
    }
    await expect(page.getByRole('listitem')).toHaveCount(16)
    await expect(add).toBeDisabled()
  })

  test('validates an empty draft on save', async ({ page }) => {
    await page.getByRole('button', { name: 'Save funnel' }).click()
    await expect(page.getByText('A funnel name is required.')).toBeVisible()
  })

  test('switches a step matcher between page view and event', async ({ page }) => {
    const firstStep = page.getByRole('listitem').first()
    await firstStep.getByRole('radio', { name: 'Event' }).click()
    await expect(firstStep.getByRole('radio', { name: 'Event' })).toHaveAttribute(
      'aria-checked',
      'true',
    )
  })
})
