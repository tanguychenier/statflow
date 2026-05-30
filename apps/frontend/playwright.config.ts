import { defineConfig } from '@playwright/test'

/**
 * Form-factor viewports, shared across every browser engine so the matrix
 * stays consistent. Tablet is portrait iPad-class; mobile is large-phone-class.
 */
const viewports = {
  desktop: { width: 1440, height: 900 },
  tablet: { width: 834, height: 1112 },
  mobile: { width: 390, height: 844 },
} as const

/**
 * isMobile / touch emulation is a Chromium-only capability in Playwright.
 * Chrome and Edge tablet/mobile projects therefore get full touch emulation;
 * Firefox tablet/mobile projects emulate the viewport size only.
 */
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: process.env.CI
    ? [['github'], ['html', { open: 'never' }]]
    : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:6006',
    // Headed by default for local runs (real browser windows, forwarded to the
    // host X display); CI overrides to headless via HEADED unset.
    headless: process.env.HEADED === '0' || (!!process.env.CI && process.env.HEADED !== '1'),
    trace: 'on-first-retry',
    video: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chrome-desktop', use: { browserName: 'chromium', channel: 'chrome', viewport: viewports.desktop } },
    { name: 'chrome-tablet', use: { browserName: 'chromium', channel: 'chrome', viewport: viewports.tablet, isMobile: true, hasTouch: true } },
    { name: 'chrome-mobile', use: { browserName: 'chromium', channel: 'chrome', viewport: viewports.mobile, isMobile: true, hasTouch: true } },
    { name: 'edge-desktop', use: { browserName: 'chromium', channel: 'msedge', viewport: viewports.desktop } },
    { name: 'edge-tablet', use: { browserName: 'chromium', channel: 'msedge', viewport: viewports.tablet, isMobile: true, hasTouch: true } },
    { name: 'edge-mobile', use: { browserName: 'chromium', channel: 'msedge', viewport: viewports.mobile, isMobile: true, hasTouch: true } },
    { name: 'firefox-desktop', use: { browserName: 'firefox', viewport: viewports.desktop } },
    { name: 'firefox-tablet', use: { browserName: 'firefox', viewport: viewports.tablet } },
    { name: 'firefox-mobile', use: { browserName: 'firefox', viewport: viewports.mobile } },
  ],
})
