/** @type {import('@stryker-mutator/core').PartialStrykerOptions} */
export default {
  packageManager: 'pnpm',
  reporters: ['html', 'clear-text', 'progress', 'dashboard'],
  testRunner: 'vitest',
  coverageAnalysis: 'perTest',
  vitest: {
    configFile: 'vitest.config.ts',
  },
  mutate: [
    'src/**/*.ts',
    'src/**/*.vue',
    '!src/**/*.d.ts',
    '!src/**/*.stories.ts',
    '!src/main.ts',
    '!src/i18n/locales/**',
  ],
  thresholds: {
    high: 80,
    low: 70,
    break: 60,
  },
  dashboard: {
    project: 'github.com/tanguychenier/statflow',
    version: 'main',
  },
}
