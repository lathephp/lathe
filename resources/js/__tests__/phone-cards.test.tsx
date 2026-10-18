import { fireEvent, screen, within } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { FilterPanel } from '../index/lazy';
import { renderPage } from './support';

beforeAll(async () => {
  await FilterPanel.preload();
});

afterEach(() => {
  vi.restoreAllMocks();
});

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };
const columns = [
  { key: 'number', label: 'Number', component: 'text', sortable: false, sortKey: 'number' },
  { key: 'name', label: 'Name', component: 'text', sortable: false, sortKey: 'name' },
  { key: 'status', label: 'Status', component: 'text', sortable: false, sortKey: 'status' },
  { key: 'total_cents', label: 'Total', component: 'text', sortable: false, sortKey: 'total_cents' },
  { key: 'customer', label: 'Customer', component: 'belongs-to-field', sortable: false, sortKey: 'customer' },
];
const cell = (display: string) => ({ value: display, display });
const row = {
  id: 7,
  title: 'ORD-00007',
  cells: { number: cell('ORD-00007'), name: cell('Blue mug'), status: cell('Paid'), total_cents: cell('$171.16'), customer: { value: { value: 3, display: 'Gerard Reichert', href: '/lathe/resources/customers/3' }, display: 'Gerard Reichert' } },
  abilities: { view: true },
};

function renderOrders(phoneCard: Record<string, string | null>, url = '/lathe/resources/orders') {
  return renderPage(
    'Resources/Index',
    {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
      table: { columns, sortKeys: [], rows: [row], rowKey: 'id', phoneCard },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      filters: { available: [{ key: 'status', name: 'Status', component: 'select-filter', options: [{ label: 'Paid', value: 'paid' }], remote: false, immediate: false, placement: 'panel', default: null }], applied: {} },
      count: { value: 2552, exact: true, capped: false, estimated: false },
    },
    { url },
  );
}

describe('phone cards', () => {
  it('draws each row as a card below 768 px, with its title, badge, value and relation', () => {
    renderOrders({ title: null, badge: 'status', value: 'total_cents', meta: 'customer' });
    const cards = document.querySelector<HTMLElement>('[data-lathe-cards]');

    expect(cards?.className).toContain('md:hidden');
    expect(screen.getByRole('region', { name: 'Orders table' }).className).toContain('max-md:hidden');

    const card = within(cards as HTMLElement).getByRole('link', { name: /ORD-00007/ });

    expect(card.getAttribute('href')).toBe('/lathe/resources/orders/7');
    expect(card.textContent).toContain('Paid');
    expect(card.textContent).toContain('$171.16');
    expect(card.textContent).toContain('Gerard Reichert');
    // The card is the one link: a relation's own link inside it would open the related record instead.
    expect(card.querySelectorAll('a')).toHaveLength(0);
  });

  it('takes the title phoneCard() names', () => {
    renderOrders({ title: 'name', badge: null, value: 'total_cents', meta: null });

    expect(within(document.querySelector('[data-lathe-cards]') as HTMLElement).getByRole('link').textContent).toContain('Blue mug');
  });

  it('opens Filter as a bottom sheet whose button names the count', () => {
    renderOrders({ title: null, badge: 'status', value: 'total_cents', meta: 'customer' }, '/lathe/resources/orders?filter[status]=paid');

    fireEvent.click(screen.getByRole('button', { name: 'Filter 1' }));
    const sheet = screen.getByRole('dialog', { name: 'Filters' });

    expect(sheet.className).toContain('max-md:fixed');

    fireEvent.click(within(sheet).getByRole('button', { name: 'Show 2,552 orders' }));

    expect(screen.queryByRole('dialog', { name: 'Filters' })).toBeNull();
  });
});
