// @vitest-environment jsdom
import { act, renderHook, waitFor } from '@testing-library/react';
import { createElement, type ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { navigating } from '../data';
import {
  on,
  off,
  QueryClientProvider,
  queryClient,
  queryKeys,
  useDebounce,
  useExecuteAction,
  useInlineField,
  useNavigationProgress,
  useResourceActions,
  useResourceDetail,
  useResourceIndex,
  useWidgetList,
  useWidgetValue,
} from '../index';

function wrapper({ children }: { children: ReactNode }) {
  return createElement(QueryClientProvider, { client: queryClient }, children);
}

function respond(body: unknown) {
  const fetch = vi.fn<(input: string, init?: RequestInit) => Promise<Response>>(() => Promise.resolve(new Response(JSON.stringify(body), { status: 200 })));
  vi.stubGlobal('fetch', fetch);

  return fetch;
}

afterEach(() => {
  vi.unstubAllGlobals();
  queryClient.clear();
});

describe('useWidgetValue', () => {
  it('reads a dashboard card value and returns the five read fields', async () => {
    const value = { name: 'Revenue', uriKey: 'revenue', component: 'value-widget', value: 42 };
    const fetch = respond(value);

    const { result } = renderHook(() => useWidgetValue({ dashboard: 'main' }, 'revenue'), { wrapper });
    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });

    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/dashboards/main/widgets/revenue');
    expect(Object.keys(result.current).sort()).toEqual(['data', 'error', 'isFetching', 'isLoading', 'refetch']);
    expect(result.current.data).toEqual(value);
    expect(result.current.error).toBeNull();
  });
});

describe('the read hooks', () => {
  it('lists the cards of a lens and unwraps the widgets key', async () => {
    const fetch = respond({ widgets: [{ uriKey: 'revenue' }] });

    const { result } = renderHook(() => useWidgetList({ resource: 'orders', lens: 'late' }, { resourceId: 7 }), { wrapper });
    await waitFor(() => {
      expect(result.current.data).toEqual([{ uriKey: 'revenue' }]);
    });

    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/lenses/late/widgets?resourceId=7');
  });

  it('reads an index page with its URL parameters', async () => {
    const fetch = respond({ table: { rows: [] } });

    const { result } = renderHook(() => useResourceIndex('orders', { page: 2, sort: '-total' }), { wrapper });
    await waitFor(() => {
      expect(result.current.data).toEqual({ table: { rows: [] } });
    });

    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders?page=2&sort=-total');
  });

  it('reads one record from its peek payload', async () => {
    const fetch = respond({ id: 5, title: 'Order 5' });

    const { result } = renderHook(() => useResourceDetail('orders', 5), { wrapper });
    await waitFor(() => {
      expect(result.current.data).toEqual({ id: 5, title: 'Order 5' });
    });

    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/5/peek');
  });

  it('reads the actions of one record', async () => {
    const fetch = respond({ actions: [], pivotActions: [], counts: {} });

    const { result } = renderHook(() => useResourceActions('orders', { resourceId: 5 }), { wrapper });
    await waitFor(() => {
      expect(result.current.data).toEqual({ actions: [], pivotActions: [], counts: {} });
    });

    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/actions?resourceId=5');
  });

  it('answers a failed read with its ApiError', async () => {
    vi.stubGlobal('fetch', vi.fn(() => Promise.resolve(new Response('{"message":"Gone"}', { status: 404 }))));

    const { result } = renderHook(() => useResourceDetail('orders', 9), { wrapper });
    await waitFor(() => {
      expect(result.current.error).toMatchObject({ status: 404, data: { message: 'Gone' } });
    });
  });
});

describe('useExecuteAction', () => {
  it('posts the action and its selection, emits actions:ran and marks the reads of the Resource stale', async () => {
    const run = { result: null, permitted: 2, skipped: 0, failed: 0, errors: [] };
    const fetch = respond(run);
    const heard = vi.fn();
    on('actions:ran', heard);
    queryClient.setQueryData(queryKeys.resourceIndex('orders', {}), { table: { rows: [] } });

    const { result } = renderHook(() => useExecuteAction('orders'), { wrapper });
    let answer: unknown;
    await act(async () => {
      answer = await result.current.execute({ action: 'mark-paid', selection: { mode: 'ids', ids: [1, 2] } });
    });
    off('actions:ran', heard);

    expect(answer).toEqual(run);
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/action');
    expect(JSON.parse(fetch.mock.calls[0]?.[1]?.body as string)).toEqual({ fields: {}, action: 'mark-paid', selection: { mode: 'ids', ids: [1, 2] } });
    expect(heard).toHaveBeenCalledWith({ action: 'mark-paid' });
    expect(queryClient.getQueryState(queryKeys.resourceIndex('orders', {}))?.isInvalidated).toBe(true);
  });
});

describe('useInlineField', () => {
  it('patches the field with the row token and puts the reply cells in the cached page, without a refetch', async () => {
    const fetch = respond({ fields: { status: { value: 'paid', display: 'Paid' } }, updatedAt: 't2' });
    const key = queryKeys.resourceIndex('orders', {});
    queryClient.setQueryData(key, {
      table: { rows: [{ id: 3, title: 'Order 3', cells: { status: { value: 'new', display: 'New' } }, abilities: {}, updatedAt: 't1' }] },
    });

    const { result } = renderHook(() => useInlineField('orders', 3, 'status'), { wrapper });
    await act(async () => {
      await result.current.save('paid');
    });

    expect(fetch).toHaveBeenCalledTimes(1);
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/3/field/status');
    expect(JSON.parse(fetch.mock.calls[0]?.[1]?.body as string)).toEqual({ value: 'paid', _updated_at: 't1' });
    expect(queryClient.getQueryData(key)).toEqual({
      table: { rows: [{ id: 3, title: 'Order 3', cells: { status: { value: 'paid', display: 'Paid' } }, abilities: {}, updatedAt: 't2' }] },
    });
  });

  it('shows the value while the save runs', async () => {
    let finish: (response: Response) => void = () => undefined;
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>((resolve) => { finish = resolve; })));

    const { result } = renderHook(() => useInlineField('orders', 3, 'status'), { wrapper });
    let saving: Promise<unknown> = Promise.resolve();
    act(() => {
      saving = result.current.save('paid');
    });

    await waitFor(() => {
      expect(result.current.pending).toEqual({ value: 'paid' });
    });

    await act(async () => {
      finish(new Response('{"fields":{}}', { status: 200 }));
      await saving;
    });

    expect(result.current.pending).toBeNull();
  });
});

describe('useNavigationProgress', () => {
  it('follows the visit the panel reports', () => {
    const { result } = renderHook(() => useNavigationProgress());
    expect(result.current).toBe(false);

    act(() => {
      navigating.set(true);
    });
    expect(result.current).toBe(true);

    act(() => {
      navigating.set(false);
    });
    expect(result.current).toBe(false);
  });
});

describe('useDebounce', () => {
  it('answers the new value only after the delay', () => {
    vi.useFakeTimers();
    const { result, rerender } = renderHook(({ value }) => useDebounce(value, 300), { initialProps: { value: 'a' } });

    rerender({ value: 'ab' });
    act(() => {
      vi.advanceTimersByTime(299);
    });
    expect(result.current).toBe('a');

    act(() => {
      vi.advanceTimersByTime(1);
    });
    expect(result.current).toBe('ab');
    vi.useRealTimers();
  });
});
