import { screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

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

function renderOrders(phoneCard: Record<string, string | null>) {
  return renderPage(
    'Resources/Index',
    {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
      table: { columns, sortKeys: [], rows: [row], rowKey: 'id', phoneCard },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      filters: { available: [{ key: 'status', name: 'Status', component: 'select-filter', options: [{ label: 'Paid', value: 'paid' }], remote: false, immediate: false, placement: 'panel', default: null }], applied: {} },
      count: { value: 2552, exact: true, capped: false, estimated: false },
    },
    { url: '/lathe/resources/orders' },
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

  it('stacks the title and badge, the relation, then the value, as the mobile board does', () => {
    renderOrders({ title: null, badge: 'status', value: 'total_cents', meta: 'customer' });
    const card = within(document.querySelector<HTMLElement>('[data-lathe-cards]') as HTMLElement).getByRole('link', { name: /ORD-00007/ });

    expect(Array.from(card.children, (line) => line.textContent)).toEqual(['ORD-00007Paid', 'Gerard Reichert', '$171.16']);
  });

  it('marks previous and next, the only pager buttons a phone keeps', async () => {
    renderOrders({ title: null, badge: 'status', value: 'total_cents', meta: 'customer' });

    // The pager's code loads after the rows paint.
    await screen.findByRole('button', { name: 'Previous page' });
    expect(Array.from(document.querySelectorAll('[data-step]'), (button) => button.getAttribute('data-step'))).toEqual(['previous', 'next']);
  });

  it('takes the title phoneCard() names', () => {
    renderOrders({ title: 'name', badge: null, value: 'total_cents', meta: null });

    expect(within(document.querySelector('[data-lathe-cards]') as HTMLElement).getByRole('link').textContent).toContain('Blue mug');
  });
});
