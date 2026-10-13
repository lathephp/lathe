import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// Per-folder floors; scripts/check-ratchet.mjs fails a change that lowers one.
const floors = JSON.parse(readFileSync(new URL('coverage-floors.json', import.meta.url), 'utf8')) as Record<
  string,
  { statements: number }
>;

export default defineConfig({
  test: {
    coverage: {
      provider: 'v8',
      include: ['packages/*/src/**', 'resources/js/**'],
      exclude: ['**/__tests__/**', '**/*.test.{ts,tsx}'],
      reporter: ['text-summary'],
      thresholds: floors,
    },
    projects: [
      {
        // The packages export their dist, which a fresh checkout lacks and which the ui
        // build makes throw on load, so the panel tests read the sources.
        resolve: {
          alias: {
            '@lathephp/core': fileURLToPath(new URL('packages/core/src/index.ts', import.meta.url)),
            '@lathephp/ui': fileURLToPath(new URL('packages/ui/src/index.ts', import.meta.url)),
          },
        },
        test: {
          name: 'app',
          environment: 'jsdom',
          include: ['resources/js/**/*.test.{ts,tsx}'],
          setupFiles: ['resources/js/__tests__/setup.ts'],
        },
      },
      {
        test: {
          name: 'packages',
          environment: 'node',
          include: ['packages/*/src/**/*.test.{ts,tsx}'],
        },
      },
    ],
  },
});
