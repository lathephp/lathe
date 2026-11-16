import { router } from '@inertiajs/react';
import { queryClient } from '@lathephp/core';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { WidgetDescriptor } from '../widgets/types';
import { renderPage } from './support';

beforeEach(() => {
  queryClient.clear();
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

const names: Record<string, string> = { revenue: 'Revenue', orders: 'Orders', 'orders-by-status': 'Orders by status', 'paid-orders': 'Paid orders' };

function descriptor(uriKey: string): WidgetDescriptor {
  return {
    name: names[uriKey] ?? uriKey,
    uriKey,
    component: 'value-widget',
    width: '1/4',
    columns: 3,
    height: 'fixed',
    help: null,
    helpWidth: 250,
    refreshEvery: 0,
    refreshWhenActionsRun: false,
    refreshWhenFiltersChange: false,
    onlyOnDetail: false,
    requestsValue: true,
    ranges: [],
    csv: false,
  };
}

const value = (uriKey: string, number: number) => ({
  name: names[uriKey],
  uriKey,
  component: 'value-widget',
  value: { value: number, previous: null, change: null, previousLabel: null, prefix: null, suffix: null, suffixInflection: true, format: null, allowZeroResult: false, copyable: false },
});

function renderDashboard(customizable: boolean, shown: string[], hidden: string[] = []) {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const key = /widgets\/([^/?]+)$/.exec(input as string)?.[1] ?? '';

    return Promise.resolve(new Response(JSON.stringify(value(key, shown.indexOf(key) + 10)), { status: 200 }));
  });
  vi.spyOn(router, 'reload').mockImplementation(() => undefined);
  renderPage('Dashboard', {
    dashboard: { uriKey: 'main', name: 'Sales', description: null, icon: null, showRefreshButton: false, customizable, customized: false, ranges: [], range: null },
    widgets: shown.map(descriptor),
    hiddenWidgets: hidden.map(descriptor),
  });
}

const order = () => [...document.querySelectorAll<HTMLElement>('[data-lathe-widget]')].map((card) => card.dataset.latheWidget);

function card(uriKey: string): HTMLElement {
  const found = document.querySelector<HTMLElement>(`[data-lathe-widget="${uriKey}"]`);

  if (found === null) {
    throw new Error(`No card ${uriKey}.`);
  }

  return found;
}

describe('customize mode', () => {
  it('offers Customize on a customizable dashboard', async () => {
    renderDashboard(true, ['orders', 'revenue']);

    fireEvent.click(screen.getByRole('button', { name: 'Customize' }));

    expect(await screen.findByText('Editing layout')).toBeTruthy();
    expect(screen.getByText(/Drag a card by its handle to move it\./).textContent).toBe(
      'Drag a card by its handle to move it. Change its size or hide it from its ⋯ menu. With a keyboard, focus the handle, press Space, then use the arrow keys. The layout is saved for your account.',
    );
    expect(screen.queryByRole('button', { name: 'Refresh' })).toBeNull();
    expect(['Reset to default', 'Cancel', 'Save layout'].map((name) => screen.getByRole('button', { name }).textContent)).toEqual(['Reset to default', 'Cancel', 'Save layout']);

    fireEvent.click(within(card('orders')).getByRole('button', { name: 'Menu for Orders' }));
    const menu = await screen.findByRole('menu');

    expect(within(menu).getAllByRole('menuitemradio').map((item) => [item.textContent, item.getAttribute('aria-checked')])).toEqual([
      ['Small3 of 12', 'true'],
      ['Medium4 of 12', 'false'],
      ['Large6 of 12', 'false'],
      ['Wide8 of 12', 'false'],
      ['Full12 of 12', 'false'],
    ]);
    expect(within(menu).getByRole('menuitem').textContent).toBe('Hide from this dashboard');
  });

  describe('the size menu', () => {
    async function opened() {
      renderDashboard(true, ['orders', 'revenue']);
      fireEvent.click(screen.getByRole('button', { name: 'Customize' }));
      await screen.findByText('Editing layout');
      const trigger = within(card('orders')).getByRole('button', { name: 'Menu for Orders' });
      trigger.focus();
      fireEvent.click(trigger);
      const menu = await screen.findByRole('menu');

      return { trigger, menu, size: (name: string) => within(menu).getByRole('menuitemradio', { name: new RegExp(`^${name}`) }) };
    }

    it('is the kit menu on the body, with the kit item on Hide', async () => {
      const { menu } = await opened();

      expect(menu.getAttribute('data-slot')).toBe('dropdown-menu-content');
      expect(within(menu).getByRole('menuitem', { name: 'Hide from this dashboard' }).getAttribute('data-slot')).toBe('dropdown-menu-item');
      expect(menu.closest('#content')).toBeNull();
      expect(menu.closest('[data-lathe-widget]')).toBeNull();
    });

    it('moves down from the first size with ArrowDown and to the last item with End', async () => {
      const { size } = await opened();
      size('Small').focus();

      fireEvent.keyDown(size('Small'), { key: 'ArrowDown' });
      await waitFor(() => {
        expect(document.activeElement).toBe(size('Medium'));
      });

      fireEvent.keyDown(size('Medium'), { key: 'End' });
      await waitFor(() => {
        expect(document.activeElement).toBe(screen.getByRole('menuitem', { name: 'Hide from this dashboard' }));
      });
    });

    it('closes on Escape and gives the focus back to its button', async () => {
      const { trigger, size } = await opened();
      size('Medium').focus();

      fireEvent.keyDown(size('Medium'), { key: 'Escape' });

      await waitFor(() => {
        expect(screen.queryByRole('menu')).toBeNull();
      });
      expect(document.activeElement).toBe(trigger);
    });

    it('resizes the card to the size chosen and closes', async () => {
      const { size } = await opened();

      fireEvent.click(size('Wide'));

      await waitFor(() => {
        expect(screen.queryByRole('menu')).toBeNull();
      });
      fireEvent.click(within(card('orders')).getByRole('button', { name: 'Menu for Orders' }));
      const menu = await screen.findByRole('menu');
      expect(within(menu).getByRole('menuitemradio', { name: /^Wide/ }).getAttribute('aria-checked')).toBe('true');
    });
  });

  it('has no Customize and no Hide on a dashboard that is not customizable', async () => {
    renderDashboard(false, ['orders', 'revenue']);

    expect(screen.queryByRole('button', { name: 'Customize' })).toBeNull();

    await waitFor(() => {
      expect(within(card('orders')).queryByRole('status')).toBeNull();
    });
    for (const [key, name] of [
      ['orders', 'Orders'],
      ['revenue', 'Revenue'],
    ] as const) {
      fireEvent.click(within(card(key)).getByRole('button', { name: `Menu for ${name}` }));
      expect(within(card(key)).getByRole('menu').textContent).toBe('Refresh');
      expect(screen.queryByText('Hide from this dashboard')).toBeNull();
    }
  });

  it('lists the hidden cards and adds one to the end of the page', async () => {
    renderDashboard(true, ['revenue', 'orders'], ['paid-orders']);

    fireEvent.click(screen.getByRole('button', { name: 'Customize' }));
    const panel = await screen.findByRole('region', { name: 'Add a widget' });

    expect(within(panel).getByText('Widgets on Sales that are hidden. A new card goes to the end of the page.')).toBeTruthy();
    expect(within(panel).getByText('Paid orders')).toBeTruthy();

    fireEvent.click(within(panel).getByRole('button', { name: 'Add Paid orders' }));

    expect(order()).toEqual(['revenue', 'orders', 'paid-orders']);
    expect(within(panel).getByText('No hidden widgets.')).toBeTruthy();
  });

  it('draws the move handle and the size menu button as ghost panel buttons', async () => {
    renderDashboard(true, ['orders', 'revenue', 'orders-by-status']);

    fireEvent.click(screen.getByRole('button', { name: 'Customize' }));

    expect((await screen.findByRole('button', { name: 'Move Orders' })).classList.contains('cn-button-variant-ghost')).toBe(true);
    expect(within(card('orders')).getByRole('button', { name: 'Menu for Orders' }).classList.contains('cn-button-variant-ghost')).toBe(true);
  });

  it('moves a card with the keyboard and says so', async () => {
    renderDashboard(true, ['orders', 'revenue', 'orders-by-status']);

    fireEvent.click(screen.getByRole('button', { name: 'Customize' }));
    const handle = await screen.findByRole('button', { name: 'Move Orders' });

    act(() => {
      handle.focus();
    });
    fireEvent.keyDown(handle, { key: ' ' });

    expect(screen.getByText('Picked up Orders. Use the arrow keys to move it, then Space to drop it.')).toBeTruthy();

    fireEvent.keyDown(handle, { key: 'ArrowRight' });

    expect(order()).toEqual(['revenue', 'orders', 'orders-by-status']);
    expect(screen.getByText('Orders moved to place 2 of 3.')).toBeTruthy();

    fireEvent.keyDown(handle, { key: ' ' });

    expect(screen.getByText('Orders dropped.')).toBeTruthy();
    expect(order()).toEqual(['revenue', 'orders', 'orders-by-status']);
    await waitFor(() => {
      expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Move Orders' }));
    });
  });

});

describe('a dashboard whose cards are all hidden', () => {
  it('says the cards are hidden and points to Customize, not to the developer setup', () => {
    renderDashboard(true, [], ['orders', 'revenue']);

    expect(screen.getByText('Every widget on this dashboard is hidden. Press Customize to add them back.')).toBeTruthy();
    expect(screen.queryByText('No widgets yet')).toBeNull();
    expect(screen.getByRole('button', { name: 'Customize' })).toBeTruthy();
  });
});
