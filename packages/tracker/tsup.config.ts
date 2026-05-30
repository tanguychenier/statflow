import { defineConfig } from 'tsup';

const VERSION = JSON.stringify(process.env['npm_package_version'] ?? '0.1.0');

const outExtension = ({ format }: { format: string }): { js: string } => {
  if (format === 'iife') return { js: '.global.js' };
  if (format === 'cjs') return { js: '.cjs' };
  return { js: '.js' };
};

const esbuild = (opts: { pure?: string[] }): void => {
  opts.pure = ['console.log', 'console.debug'];
};

export default defineConfig([
  // Core entry. Its static graph is deliberately minimal — pageview, SPA and the
  // transport/queue/envelope/config/privacy/ids machinery only — and it loads
  // the behavior and heatmap bundles by URL via import(), so it pulls in no
  // other chunk. It therefore builds as a single self-contained file with no
  // behavioral collectors in its graph (docs/tracker/tracker-design.md §7).
  {
    entry: { tracker: 'src/index.ts' },
    format: ['iife', 'esm', 'cjs'],
    globalName: 'StatflowTracker',
    target: 'es2017',
    minify: true,
    treeshake: true,
    dts: true,
    clean: true,
    define: { __TRACKER_VERSION__: VERSION, __BROWSER__: 'true' },
    esbuildOptions: esbuild,
    outExtension,
  },
  // Lazily-loaded behavioral collectors (clicks, rage/dead clicks, scroll,
  // forms, engagement, visibility, vitals, errors). Built like the heatmap
  // bundle: a self-contained artifact never imported by the core, fetched via
  // import() the first time a behavioral feature is enabled. Code-splitting is
  // OFF so it carries its own copy of the shared core helpers and the core's
  // critical path stays a single file (docs/tracker/tracker-design.md §7).
  {
    entry: { behavior: 'src/behavior/index.ts' },
    format: ['iife', 'esm', 'cjs'],
    globalName: 'StatflowBehavior',
    target: 'es2017',
    minify: true,
    treeshake: true,
    dts: true,
    clean: false,
    define: { __TRACKER_VERSION__: VERSION, __BROWSER__: 'true' },
    esbuildOptions: esbuild,
    outExtension,
  },
  // Separately built, lazily-loaded heatmap bundle. It is NOT imported by the
  // core entry; the product fetches it via import() on demand
  // (docs/tracker/tracker-design.md §7–§8).
  {
    entry: { 'tracker-heatmap': 'src/heatmap/index.ts' },
    format: ['iife', 'esm', 'cjs'],
    globalName: 'StatflowHeatmap',
    target: 'es2017',
    minify: true,
    treeshake: true,
    dts: true,
    clean: false,
    define: { __TRACKER_VERSION__: VERSION, __BROWSER__: 'true' },
    esbuildOptions: esbuild,
    outExtension,
  },
]);
