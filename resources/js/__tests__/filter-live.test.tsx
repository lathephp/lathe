import { router } from '@inertiajs/react';
import { act, fireEvent, screen, within } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { FilterPanel as LazyFilterPanel } from '../index/lazy';
import type { AvailableFilter, Count } from '../index/types';
import { renderPage } from './support';

// Loaded once, the panel draws on the press itself.
beforeAll(async () => {
  await LazyFilterPanel.preload();
});

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
});

const status: AvailableFilter = {
  key: 'status',
  name: 'Status',
  component: 'select-filter',
  options: [
    { label: 'Pending', value: 'pending' },
    { label: 'Paid', value: 'paid' },
  ],
  remote: false,
  immediate: false,
  placement: 'panel',
  default: null,
};

const total: AvailableFilter = { ...status, key: 'total', name: 'Total', component: 'range-filter', options: null };

const paidCount: Count = { value: 2552, exact: true, capped: false, estimated: false };

function renderIndex(url: string, count: Count | null | undefined) {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
        perPageOptions: [25, 50, 100],
        searchable: false,
        softDeletes: false,
        abilities: { create: false },
        definition: { debounce: 0.5 },
      },
      table: { columns: [], sortKeys: [], rows: [], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      filters: { available: [status, total], applied: {} },
      staleFilters: [],
      count,
    },
    { url },
  );
}

function mockVisit() {
  return vi.spyOn(router, 'visit').mockImplementation(() => undefined);
}

function visitData(visit: ReturnType<typeof mockVisit>, call = 0): unknown {
  return (visit.mock.calls[call]?.[1] as { data: unknown }).data;
}

/**
 * Picks an option as a pointer does, then runs on a fake clock from the click on. The list
 * opens on the real clock, and the press comes first because Base UI drops a bare click.
 */
async function pick(trigger: HTMLElement, label: string) {
  fireEvent.mouseDown(trigger, { button: 0 });
  fireEvent.mouseUp(trigger, { button: 0 });
  const option = await screen.findByRole('option', { name: label });
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
  fireEvent.pointerDown(option);
  fireEvent.click(option);
  expect(screen.queryByRole('listbox')).toBeNull();
}

async function advance(ms: number) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms);
  });
}

describe('live filters', () => {
  it('visits 300 ms after a status is chosen, with no Apply button', async () => {
    const visit = mockVisit();
    renderIndex('/lathe/resources/orders', paidCount);

    fireEvent.click(screen.getByRole('button', { name: 'Filter' }));
    await pick(screen.getByLabelText('Status'), 'Paid');
    await advance(299);

    expect(visit).not.toHaveBeenCalled();

    await advance(1);

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visitData(visit)).toEqual({ filter: { status: 'paid' } });
    expect(screen.queryByRole('button', { name: 'Apply' })).toBeNull();
  });

  it('sends three changes made within 300 ms as one visit', async () => {
    const visit = mockVisit();
    renderIndex('/lathe/resources/orders', paidCount);

    fireEvent.click(screen.getByRole('button', { name: 'Filter' }));
    await pick(screen.getByLabelText('Status'), 'Paid');
    await advance(100);
    fireEvent.change(screen.getByLabelText('Min'), { target: { value: '100' } });
    await advance(100);
    fireEvent.change(screen.getByLabelText('Max'), { target: { value: '500' } });
    await advance(299);

    expect(visit).not.toHaveBeenCalled();

    await advance(1);

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visitData(visit)).toEqual({ filter: { status: 'paid', total: { min: '100', max: '500' } } });
  });

  it('drops a change still waiting when a chip is removed meanwhile', async () => {
    const visit = mockVisit();
    renderIndex('/lathe/resources/orders?filter[status]=paid', paidCount);

    fireEvent.click(screen.getByRole('button', { name: 'Filter 1' }));
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    fireEvent.change(screen.getByLabelText('Min'), { target: { value: '100' } });
    fireEvent.click(screen.getByRole('button', { name: 'Remove Status filter' }));
    await advance(400);

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visitData(visit)).toEqual({});
  });

  it('drops a change still waiting once the person goes to another page', async () => {
    const visit = mockVisit();
    renderIndex('/lathe/resources/orders', paidCount);

    fireEvent.click(screen.getByRole('button', { name: 'Filter' }));
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    fireEvent.change(screen.getByLabelText('Min'), { target: { value: '100' } });
    // A click on a row starts a visit to the record before the 300 ms are up.
    document.dispatchEvent(new CustomEvent('inertia:start', { detail: { visit: { url: new URL('http://localhost/lathe/resources/orders/7'), prefetch: false, async: false } } }));
    await advance(400);

    expect(visit).not.toHaveBeenCalled();
  });

  it('closes the popover on Escape and on a press outside it', () => {
    renderIndex('/lathe/resources/orders', paidCount);

    fireEvent.click(screen.getByRole('button', { name: 'Filter' }));
    fireEvent.keyDown(document, { key: 'Escape' });

    expect(screen.queryByRole('dialog', { name: 'Filters' })).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'Filter' }));
    fireEvent.pointerDown(document.body);

    expect(screen.queryByRole('dialog', { name: 'Filters' })).toBeNull();
  });

  it('lets the popover reach past a short table, the card clipping nothing', () => {
    renderIndex('/lathe/resources/orders', paidCount);

    expect(document.querySelector('[data-lathe-card]')?.className).not.toContain('overflow-hidden');
  });

  it('shows the match count, a chip for the filter and the count on the button', () => {
    const visit = mockVisit();
    renderIndex('/lathe/resources/orders?filter[status]=paid', paidCount);

    fireEvent.click(screen.getByRole('button', { name: 'Filter 1' }));

    expect(within(screen.getByRole('dialog', { name: 'Filters' })).getByText('2,552 orders')).toBeTruthy();

    const chips = document.querySelector<HTMLElement>('[data-lathe-chips]');

    expect(chips).not.toBeNull();
    expect(within(chips as HTMLElement).getByText('Status: Paid')).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Remove Status filter' }));

    expect(visitData(visit)).toEqual({});
  });

  it('holds the foot with a placeholder while the count loads', () => {
    renderIndex('/lathe/resources/orders?filter[status]=paid', undefined);

    fireEvent.click(screen.getByRole('button', { name: 'Filter 1' }));

    const foot = document.querySelector('[data-lathe-filter-count]');

    expect(foot?.textContent).toBe('');
    expect(foot?.querySelector('[data-placeholder]')).not.toBeNull();
  });

  it('draws no chips row without a filter', () => {
    renderIndex('/lathe/resources/orders', paidCount);

    expect(document.querySelector('[data-lathe-chips]')).toBeNull();
    expect(screen.getByRole('button', { name: 'Filter' })).toBeTruthy();
  });

  it('opens the popover out of the card flow', () => {
    const { container } = renderIndex('/lathe/resources/orders', paidCount);
    const card = container.querySelector('[data-lathe-card]') as HTMLElement;
    const before = Array.from(card.children);

    fireEvent.click(screen.getByRole('button', { name: 'Filter' }));

    const after = Array.from(card.children);

    expect(after.length).toBe(before.length);
    expect(after.every((child, index) => child === before[index])).toBe(true);
    expect(screen.getByRole('dialog', { name: 'Filters' }).classList.contains('absolute')).toBe(true);
  });
});
