import { router } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi, type MockInstance } from 'vitest';
import { runAction } from '../actions/runAction';
import { flushResourceOf } from '../lib/instant';

let flush: MockInstance<typeof router.flushByCacheTags>;

beforeEach(() => {
  flush = vi.spyOn(router, 'flushByCacheTags').mockImplementation(() => undefined);
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

describe('flushResourceOf', () => {
  it('flushes the resource named in the URL', () => {
    flushResourceOf('/lathe/api/resources/orders/12/inline');

    expect(flush.mock.calls).toEqual([['resource:orders']]);
  });

  it('ignores the query string', () => {
    flushResourceOf('/lathe/api/resources/order-items?x=1');

    expect(flush.mock.calls).toEqual([['resource:order-items']]);
  });

  it('flushes nothing for a URL without a resource', () => {
    flushResourceOf('/lathe/api/notifications/read');

    expect(flush).not.toHaveBeenCalled();
  });
});

describe('runAction with a file', () => {
  const selection = { mode: 'ids', ids: [1] } as never;
  const fields = { attachment: new File(['x'], 'a.txt') };

  it('flushes the resource of its URL after a 200', async () => {
    vi.stubGlobal('fetch', vi.fn(() => Promise.resolve(new Response('{}', { status: 200 }))));

    await runAction('/lathe/api/resources/orders/actions', 'import', selection, fields);

    expect(flush.mock.calls).toEqual([['resource:orders']]);
  });

  it('flushes nothing after a failure', async () => {
    vi.stubGlobal('fetch', vi.fn(() => Promise.resolve(new Response('{}', { status: 422 }))));

    await runAction('/lathe/api/resources/orders/actions', 'import', selection, fields).catch(() => undefined);

    expect(flush).not.toHaveBeenCalled();
  });
});
