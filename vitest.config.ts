import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    projects: [
      {
        test: {
          name: 'app',
          environment: 'jsdom',
          include: ['resources/js/**/*.test.{ts,tsx}'],
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
