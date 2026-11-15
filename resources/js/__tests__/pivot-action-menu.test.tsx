import { QueryClientProvider, queryClient } from '@lathephp/core';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
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

function renderMenu(ids: number[], actions = [moveToFirst]) {
  return render(
    <QueryClientProvider client={queryClient}>
      <div id="content">
        <PivotActionMenu resource="tags" via="products/1/tags" actions={actions} ids={ids} queryKey={['relation', 'products', '1', 'tags']} />
      </div>
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
    fireEvent.click(await screen.findByRole('menuitem', { name: 'Move to First' }));

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledTimes(1);
    });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/tags/action');
    expect(JSON.parse(fetch.mock.calls[0]?.[1]?.body as string)).toMatchObject({ action: 'move-to-first', selection: { mode: 'ids', ids: [3, 5], via: 'products/1/tags' } });
  });

  it('shows no Actions button until a row is ticked when no pivot action is standalone', () => {
    renderMenu([]);

    expect(screen.queryByRole('button', { name: 'Actions' })).toBeNull();
  });

  it('lists only the standalone pivot action with nothing ticked', async () => {
    renderMenu([], [moveToFirst, { ...moveToFirst, uriKey: 'sync-tags', name: 'Sync tags', standalone: true }]);

    fireEvent.click(screen.getByRole('button', { name: 'Actions' }));
    await screen.findByRole('menu', { name: 'Actions' });

    expect(screen.getAllByRole('menuitem').map((item) => item.textContent)).toEqual(['Sync tags']);
  });

  describe('from the keyboard', () => {
    async function opened() {
      renderMenu([3, 5], [moveToFirst, { ...moveToFirst, uriKey: 'move-to-last', name: 'Move to Last' }, { ...moveToFirst, uriKey: 'clear', name: 'Clear', destructive: true }]);
      const trigger = screen.getByRole('button', { name: 'Actions' });
      trigger.focus();
      fireEvent.click(trigger);
      const menu = await screen.findByRole('menu', { name: 'Actions' });

      return { trigger, menu, item: (name: string) => within(menu).getByRole('menuitem', { name }) };
    }

    it('is the kit menu, with the kit item on each action', async () => {
      const { menu } = await opened();

      expect(menu.getAttribute('data-slot')).toBe('dropdown-menu-content');
      expect(within(menu).getAllByRole('menuitem').map((item) => item.getAttribute('data-slot'))).toEqual(['dropdown-menu-item', 'dropdown-menu-item', 'dropdown-menu-item']);
    });

    it('moves down from the first item with ArrowDown and to the last with End', async () => {
      const { item } = await opened();
      item('Move to First').focus();

      fireEvent.keyDown(item('Move to First'), { key: 'ArrowDown' });
      await waitFor(() => {
        expect(document.activeElement).toBe(item('Move to Last'));
      });

      fireEvent.keyDown(item('Move to Last'), { key: 'End' });
      await waitFor(() => {
        expect(document.activeElement).toBe(item('Clear'));
      });
    });

    it('closes on Escape and gives the focus back to Actions', async () => {
      const { trigger, item } = await opened();
      item('Move to Last').focus();

      fireEvent.keyDown(item('Move to Last'), { key: 'Escape' });

      await waitFor(() => {
        expect(screen.queryByRole('menu', { name: 'Actions' })).toBeNull();
      });
      await waitFor(() => {
        expect(document.activeElement).toBe(trigger);
      });
    });

    it('draws its menu on the body, outside the content', async () => {
      const { menu } = await opened();

      expect(menu.closest('#content')).toBeNull();
    });
  });
});
