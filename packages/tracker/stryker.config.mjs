import { fileURLToPath } from 'url';
import { createRequire } from 'module';
import path from 'path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/** @type {import('@stryker-mutator/core').PartialStrykerOptions} */
const config = {
  testRunner: 'vitest',
  // Explicit plugin path to work around pnpm + ESM plugin discovery
  plugins: [
    path.join(__dirname, 'node_modules/@stryker-mutator/vitest-runner/dist/src/index.js'),
  ],
  mutate: [
    'src/**/*.ts',
    '!src/**/*.d.ts',
  ],
  coverageAnalysis: 'perTest',
  thresholds: {
    high: 75,
    low: 65,
    break: 55,
  },
  reporters: ['html', 'clear-text', 'progress'],
  htmlReporter: {
    fileName: 'reports/mutation/index.html',
  },
  timeoutMS: 30000,
  timeoutFactor: 2,
  disableTypeChecks: true,
  ignorePatterns: ['node_modules', 'dist', 'coverage', 'reports'],
};

export default config;
