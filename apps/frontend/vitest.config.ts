import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./tests/setup.ts'],
    include: ['src/**/*.{test,spec}.ts', 'tests/unit/**/*.{test,spec}.ts'],
    exclude: ['tests/e2e/**', 'node_modules/**'],
    testTimeout: 15000,
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html', 'lcov'],
      reportsDirectory: './coverage',
      include: ['src/**/*.{ts,vue}'],
      exclude: [
        // Entry point — bootstraps the app, not unit-testable
        'src/main.ts',
        'src/env.d.ts',
        'src/**/*.d.ts',
        'src/**/*.stories.ts',
        // Locale JSON / GeoJSON data files
        'src/i18n/locales/**',
        'src/charts/geo/**',
        // i18n config and format data — pure configuration objects, tested via integration
        'src/i18n/index.ts',
        'src/i18n/datetimeFormats.ts',
        'src/i18n/numberFormats.ts',
        // Skeleton views — fleshed out + e2e-tested by screen agents
        'src/views/**/*.vue',
        // Root application component — tested via e2e
        'src/App.vue',
        // Layout shell components — e2e integration tested by screen agents
        'src/components/layout/**/*.vue',
        // Router config — exercised by router.test.ts but excluded from line targets
        // (lazy import() factories are not executed under coverage)
        'src/router/**',
        // Re-export barrel files
        'src/**/index.ts',
        // Type definitions only
        'src/types/**',
        'src/api/types.ts',
        'src/components/DataTable/types.ts',
        // Locale store — requires full Vue app context with i18n
        'src/stores/locale.ts',
        // Composables with lifecycle hooks / browser APIs — covered via component + e2e
        'src/composables/useReducedMotion.ts',
        // ECharts-backed wrappers need a real canvas (WebGL/2d) — verified via
        // Storybook + Playwright; the pure option builders are unit-tested directly.
        'src/composables/useChart.ts',
        'src/charts/echarts.ts',
        'src/components/charts/ChartWrapper.vue',
        'src/components/charts/ChartSkeleton.vue',
        'src/components/charts/ChartEmpty.vue',
        'src/components/charts/TimeSeriesChart.vue',
        'src/components/charts/BarChart.vue',
        'src/components/charts/DonutChart.vue',
        'src/components/charts/SparkLine.vue',
        'src/components/charts/RetentionGrid.vue',
        'src/components/charts/GeoMap.vue',
        'src/components/charts/ChartCard.vue',
        'src/components/charts/HeatmapOverlay.vue',
        // Wiring that only runs in the live app shell
        'src/api/configureAuth.ts',
        // Thin Reka-UI delegating wrappers (no own logic) — verified via
        // Storybook + Playwright a11y; teleported content is brittle in jsdom.
        'src/components/ui/select/Select.vue',
        'src/components/ui/tooltip/Tooltip.vue',
        'src/components/ui/popover/Popover.vue',
        'src/components/ui/dialog/Modal.vue',
        'src/components/ui/dialog/ConfirmDialog.vue',
        'src/components/ui/tabs/Tabs.vue',
        // Store-bound composite wrappers driven by Reka popovers/dialogs — their
        // logic lives in the (separately tested) stores; UI verified via e2e.
        'src/components/DateRangePicker/DateRangePicker.vue',
        'src/components/CommandPalette/CommandPalette.vue',
        // vue-query composables + endpoint clients — exercise the live backend;
        // covered by backend integration + e2e, not jsdom unit tests.
        'src/api/composables/**',
        'src/api/endpoints/**',
      ],
      thresholds: {
        lines: 90,
        branches: 85,
        functions: 90,
        statements: 90,
      },
    },
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
})
