import { render, screen } from '@testing-library/react';
import { Suspense } from 'react';
import { describe, expect, it } from 'vitest';
import { isBuildStale } from '../lib/assets';
import { lazyNamed } from '../lib/lazy';

describe('lazyNamed', () => {
  it('marks the build stale and draws nothing when its chunk is gone, so the panel stays up', async () => {
    const Gone = lazyNamed<object>(() => Promise.reject(new TypeError('Failed to fetch dynamically imported module')));

    render(
      <main>
        <p>Orders</p>
        <Suspense fallback={null}>
          <Gone />
        </Suspense>
      </main>,
    );

    await expect(Gone.preload()).resolves.toBeUndefined();
    expect(await screen.findByText('Orders')).toBeTruthy();
    expect(isBuildStale()).toBe(true);
  });
});
