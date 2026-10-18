import { expect, it } from 'vitest';
import app from '../app.tsx?raw';

// app.tsx boots the whole panel when imported, so the test reads its setting instead.
it('shows the progress bar only for a visit that takes longer than 150 ms', () => {
  expect(app).toContain('progress: { delay: 150 }');
});
