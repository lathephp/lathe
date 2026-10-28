import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FilterPanel } from '../index/FilterPanel';
import type { AvailableFilter } from '../index/types';
import { renderPage } from './support';

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

/** The panel as a relation table draws it. */
function renderPanel() {
  const visit = vi.fn();
  render(<FilterPanel resource="orders" endpoint="/lathe/api/resources/orders/filters" available={[status, total]} softDeletes={false} state={{}} visit={visit} />);

  return visit;
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
    const visit = renderPanel();

    await pick(screen.getByLabelText('Status'), 'Paid');
    await advance(299);

    expect(visit).not.toHaveBeenCalled();

    await advance(1);

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visit).toHaveBeenCalledWith({ filter: { status: 'paid' } });
    expect(screen.queryByRole('button', { name: 'Apply' })).toBeNull();
  });

  it('sends three changes made within 300 ms as one visit', async () => {
    const visit = renderPanel();

    await pick(screen.getByLabelText('Status'), 'Paid');
    await advance(100);
    fireEvent.change(screen.getByLabelText('Min'), { target: { value: '100' } });
    await advance(100);
    fireEvent.change(screen.getByLabelText('Max'), { target: { value: '500' } });
    await advance(299);

    expect(visit).not.toHaveBeenCalled();

    await advance(1);

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visit).toHaveBeenCalledWith({ filter: { status: 'paid', total: { min: '100', max: '500' } } });
  });

  it('drops a change still waiting once the person goes to another page', async () => {
    const visit = renderPanel();

    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    fireEvent.change(screen.getByLabelText('Min'), { target: { value: '100' } });
    // A click on a row starts a visit to the record before the 300 ms are up.
    document.dispatchEvent(new CustomEvent('inertia:start', { detail: { visit: { url: new URL('http://localhost/lathe/resources/orders/7'), prefetch: false, async: false } } }));
    await advance(400);

    expect(visit).not.toHaveBeenCalled();
  });

  it('lets a toolbar popover reach past a short table, the card clipping nothing', () => {
    renderPage(
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
        count: null,
      },
      { url: '/lathe/resources/orders' },
    );

    expect(document.querySelector('[data-lathe-card]')?.className).not.toContain('overflow-hidden');
  });
});
