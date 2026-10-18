import { expect, it } from 'vitest';
import app from '../app.tsx?raw';
import { preloadPanelPages } from '../lib/pages';

// app.tsx boots the whole panel when imported, so these tests read its settings instead.
it('shows the progress bar only for a visit that takes longer than 150 ms', () => {
  expect(app).toContain('progress: { delay: 150 }');
});

it('loads the index, record and dashboard pages while the browser is idle after the first page', async () => {
  expect(app).toContain('preloadPanelPages()');

  const loaded = (await preloadPanelPages()) as { default: unknown }[];

  expect(loaded).toHaveLength(3);
  expect(loaded.every((module) => typeof module.default === 'function')).toBe(true);
});
