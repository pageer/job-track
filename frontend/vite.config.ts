import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

// The frontend build output must land in ../backend/public/build so that
// Symfony's SpaController can serve it in production (see backend/src/Controller/SpaController.php).
// In dev, Vite serves the SPA on port 5173 and proxies /api to the Symfony dev server on 8000.
export default defineConfig({
  // The trailing `(\?.*)?` lets the plugin match ids that carry a query string.
  // Vitest's Istanbul coverage provider transforms uncovered files as `<file>?v=<ts>`,
  // and without this the plugin's default `/\.[tj]sx?$/` filter misses them, leaving
  // raw JSX that the SSR transform then fails to parse.
  plugins: [react({ include: [/\.[tj]sx?(\?.*)?$/] })],
  base: '/build/',
  build: {
    outDir: '../backend/public/build',
    emptyOutDir: true,
    manifest: true,
  },
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    coverage: {
      // Istanbul (not v8): the v8 provider fabricates a single function and branch
      // for every file with no executed code, which makes those two percentages
      // meaningless. Istanbul reports the real counts for uncovered files too.
      provider: 'istanbul',
      include: ['src/**/*.{ts,tsx}'],
      exclude: [
        'src/**/*.test.{ts,tsx}',
        'src/test/**',
        'src/vite-env.d.ts',
        // App entry point: mounts React, nothing meaningful to cover in jsdom.
        'src/main.tsx',
      ],
      reporter: ['text-summary', 'json-summary', 'lcov'],
      reportsDirectory: './coverage',
    },
  },
});
