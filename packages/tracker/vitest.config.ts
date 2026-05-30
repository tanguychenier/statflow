import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['tests/**/*.test.ts'],
    exclude: ['node_modules', 'dist'],
    coverage: {
      provider: 'v8',
      include: ['src/**/*.ts'],
      exclude: ['src/**/*.d.ts'],
      reporter: ['text', 'lcov', 'html'],
      thresholds: {
        lines: 90,
        // Branch threshold is 85% rather than 90% because the optional-chaining
        // guards for non-browser environments (window === undefined, navigator ===
        // undefined) cannot be exercised in jsdom without patching globals in ways
        // that break other tests. All reachable browser-runtime branches are covered.
        branches: 85,
        functions: 90,
        statements: 90,
      },
      reportsDirectory: './coverage',
    },
    setupFiles: ['./tests/setup.ts'],
  },
});
