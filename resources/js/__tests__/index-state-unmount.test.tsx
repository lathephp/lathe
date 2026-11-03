import { act, renderHook } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { useIndexState } from '../index/useIndexState';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ url: '/lathe/resources/orders' }),
  router: {
    get: (_path: string, _query: unknown, options: { onStart: () => void; onFinish: () => void }) => {
      options.onStart();
      options.onFinish();
    },
  },
}));

afterEach(() => {
  vi.useRealTimers();
});

it('leaves no timer behind when the list unmounts before its skeleton time is up', () => {
  vi.useFakeTimers();
  const { result, unmount } = renderHook(() => useIndexState({ perPage: 25 }));

  act(() => {
    result.current.visit({ q: 'ada' });
  });
  expect(vi.getTimerCount()).toBe(1);

  unmount();

  expect(vi.getTimerCount()).toBe(0);
});
