// @vitest-environment jsdom
import { renderHook, waitFor } from '@testing-library/react';
import { createElement, type ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { QueryClientProvider, queryClient, queryKeys, useQuery } from '../index';

function wrapper({ children }: { children: ReactNode }) {
  return createElement(QueryClientProvider, { client: queryClient }, children);
}

afterEach(() => {
  vi.useRealTimers();
  queryClient.clear();
});

describe('queryClient', () => {
  it('serves a second read of one key within 60 s from the cache', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const fetcher = vi.fn(() => Promise.resolve('hit'));
    const read = () => renderHook(() => useQuery({ queryKey: queryKeys.search('ada'), queryFn: fetcher }), { wrapper });

    const first = read();
    await waitFor(() => { expect(first.result.current.data).toBe('hit'); });
    first.unmount();

    vi.advanceTimersByTime(59_000);
    const second = read();
    await waitFor(() => { expect(second.result.current.data).toBe('hit'); });

    expect(fetcher).toHaveBeenCalledTimes(1);
  });

  it('refetches a read after 61 s', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const fetcher = vi.fn(() => Promise.resolve('hit'));
    const read = () => renderHook(() => useQuery({ queryKey: queryKeys.search('ada'), queryFn: fetcher }), { wrapper });

    const first = read();
    await waitFor(() => { expect(first.result.current.data).toBe('hit'); });
    first.unmount();

    vi.advanceTimersByTime(61_000);
    const second = read();
    await waitFor(() => { expect(fetcher).toHaveBeenCalledTimes(2); });
    second.unmount();
  });
});

describe('queryKeys', () => {
  it('builds keys from the request parts', () => {
    expect(queryKeys.search('ada')).toEqual(['search', 'ada']);
    expect(queryKeys.filterOptions('users', 'role', 'ad', 2)).toEqual(['filter-options', 'users', 'role', 'ad', 2]);
    expect(queryKeys.count('users', { status: 'active' })).toEqual(['count', 'users', { status: 'active' }]);
  });
});
