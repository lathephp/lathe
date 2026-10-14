import { QueryClientProvider, queryClient } from '@lathephp/core';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PivotActionMenu } from '../actions/PivotActionMenu';
import type { ActionMeta } from '../actions/types';

vi.mock('@inertiajs/react', async (original) => ({
  ...(await original<typeof import('@inertiajs/react')>()),
  usePage: () => ({ props: { auth: { user: { email: 'admin@lathephp.com' } } } }),
}));

afterEach(() => {
  vi.restoreAllMocks();
});

const moveToFirst: ActionMeta = {
  uriKey: 'move-to-first', name: 'Move to First', icon: null, description: null, group: null, destructive: false, standalone: false,
  sole: false, pinned: false, withoutConfirmation: true, confirmText: null, confirmButtonText: 'Run Action', cancelButtonText: 'Cancel',
  size: '2xl', style: 'window', asBlob: false, showOnIndex: true, showOnDetail: true, showOnTableRow: false,
};

function renderMenu(ids: number[]) {
  return render(
    <QueryClientProvider client={queryClient}>
      <PivotActionMenu resource="tags" via="products/1/tags" actions={[moveToFirst]} ids={ids} queryKey={['relation', 'products', '1', 'tags']} />
    </QueryClientProvider>,
  );
}

describe('the pivot action menu', () => {
  it('runs a pivot action on the ticked rows through the related Resource with the via', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ result: { message: 'Moved to first.', level: 'success', effect: null }, permitted: 2, skipped: 0, failed: 0, errors: [] })),
    );
    renderMenu([3, 5]);

    fireEvent.click(screen.getByRole('button', { name: 'Actions' }));
    fireEvent.click(screen.getByRole('menuitem', { name: 'Move to First' }));

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledTimes(1);
    });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/tags/action');
    expect(JSON.parse(fetch.mock.calls[0]?.[1]?.body as string)).toMatchObject({ action: 'move-to-first', selection: { mode: 'ids', ids: [3, 5], via: 'products/1/tags' } });
  });

  it('disables a pivot action until a row is ticked', () => {
    renderMenu([]);

    fireEvent.click(screen.getByRole('button', { name: 'Actions' }));

    expect(screen.getByRole<HTMLButtonElement>('menuitem', { name: 'Move to First' }).disabled).toBe(true);
  });
});
