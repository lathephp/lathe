import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LayoutBlock, PanelBlock, TabGroupBlock } from '../fields/types';
import { RecordLayout } from '../records/RecordLayout';

afterEach(() => {
  vi.restoreAllMocks();
  window.history.replaceState(null, '', '/');
});

function field(key: string, value: unknown = key) {
  return {
    key,
    attribute: key,
    label: key.charAt(0).toUpperCase() + key.slice(1),
    component: 'text-field',
    sortable: false,
    computed: false,
    nullable: false,
    stacked: false,
    fullWidth: false,
    columnSpan: null,
    indexWidth: null,
    borderless: false,
    compact: false,
    textAlign: 'left',
    placeholder: null,
    help: null,
    helpWidth: null,
    wrapping: false,
    section: null,
    autocomplete: null,
    meta: {},
    value,
    display: String(value),
  };
}

function panel(attribute: string, fields: string[], overrides: Partial<PanelBlock> = {}): PanelBlock {
  return {
    type: 'panel',
    attribute,
    name: attribute.charAt(0).toUpperCase() + attribute.slice(1),
    fields,
    toolbar: false,
    collapsible: false,
    collapsed: false,
    hideHeading: false,
    headingInCard: false,
    help: null,
    limit: null,
    columns: null,
    columnSpan: null,
    borderless: false,
    ...overrides,
  };
}

function tabs(attribute: string, queryKey: string, names: string[], overrides: Partial<TabGroupBlock> = {}): TabGroupBlock {
  return {
    type: 'tab-group',
    attribute,
    label: null,
    queryKey,
    activeTab: 0,
    vertical: false,
    contained: false,
    columnSpan: null,
    tabs: names.map((name) => ({
      attribute: name,
      label: name.charAt(0).toUpperCase() + name.slice(1),
      icon: null,
      badge: null,
      badgeType: null,
      columns: null,
      borderless: false,
      items: [panel(`${name}-fields`, [name], { hideHeading: true })],
    })),
    ...overrides,
  };
}

// The detail page draws its own two columns, so RecordLayout's detail mode renders on its own here.
function renderDetail(layout: LayoutBlock[], keys: string[]) {
  const byKey = new Map(keys.map((key) => [key, field(key)]));

  return render(
    <RecordLayout
      detail
      blocks={layout}
      renderField={(key) => {
        const one = byKey.get(key);

        return one === undefined ? null : (
          <div>
            <div>{one.label}</div>
            <div>{one.display}</div>
          </div>
        );
      }}
    />,
  );
}

describe('tabs', () => {
  it('puts the selected tab in ?tab= and opens it again from the URL', () => {
    const { unmount } = renderDetail([tabs('main', 'tab', ['order', 'billing'])], ['order', 'billing']);

    fireEvent.click(screen.getByRole('tab', { name: 'Billing' }));
    expect(new URL(window.location.href).searchParams.get('tab')).toBe('billing');
    unmount();

    renderDetail([tabs('main', 'tab', ['order', 'billing'])], ['order', 'billing']);
    expect(screen.getByRole('tab', { name: 'Billing' }).getAttribute('aria-selected')).toBe('true');
  });

  it('keys a second group by its own query key and opens activeTab when the query names no tab', () => {
    window.history.replaceState(null, '', '/?tab=nope');
    renderDetail([tabs('main', 'tab', ['order', 'billing'], { activeTab: 1 }), tabs('more', 'tab_more', ['notes', 'items'])], ['order', 'billing', 'notes', 'items']);

    expect(screen.getByRole('tab', { name: 'Billing' }).getAttribute('aria-selected')).toBe('true');
    fireEvent.click(screen.getByRole('tab', { name: 'Items' }));
    expect(new URL(window.location.href).searchParams.get('tab_more')).toBe('items');
  });
});

describe('tab keys', () => {
  afterEach(() => {
    document.dir = '';
  });

  it('draws the group as kit tabs with one tab stop, and ArrowRight writes the query key as a click does', () => {
    renderDetail([tabs('main', 'tab', ['order', 'billing'])], ['order', 'billing']);

    const order = screen.getByRole('tab', { name: 'Order' });
    expect(order.className).toContain('cn-tabs-trigger');
    expect(order.closest('[role="tablist"]')?.className).toContain('cn-tabs-list');
    expect(order.tabIndex).toBe(0);
    expect(screen.getByRole('tab', { name: 'Billing' }).tabIndex).toBe(-1);

    order.focus();
    fireEvent.keyDown(order, { key: 'ArrowRight' });

    const billing = screen.getByRole('tab', { name: 'Billing' });
    expect(billing.getAttribute('aria-selected')).toBe('true');
    expect(document.activeElement).toBe(billing);
    expect(new URL(window.location.href).searchParams.get('tab')).toBe('billing');
  });

  it('moves forward with ArrowLeft under rtl', () => {
    document.dir = 'rtl';
    // Three tabs, so that a key's direction shows: wrapping lands two tabs on the same neighbour.
    renderDetail([tabs('main', 'tab', ['order', 'billing', 'notes'])], ['order', 'billing', 'notes']);

    fireEvent.keyDown(screen.getByRole('tab', { name: 'Order' }), { key: 'ArrowLeft' });

    expect(screen.getByRole('tab', { name: 'Billing' }).getAttribute('aria-selected')).toBe('true');
    expect(new URL(window.location.href).searchParams.get('tab')).toBe('billing');
  });

  it('moves a vertical group with ArrowDown and with the forward arrow', () => {
    renderDetail([tabs('main', 'tab', ['order', 'billing', 'notes'], { vertical: true })], ['order', 'billing', 'notes']);

    const tablist = screen.getByRole('tablist');
    // A phone draws the group as a horizontal row; md: classes turn it into a column.
    expect(tablist.getAttribute('data-orientation')).toBe('horizontal');
    expect(tablist.getAttribute('aria-orientation')).toBe('vertical');
    expect(tablist.className).toContain('md:flex-col');

    fireEvent.keyDown(screen.getByRole('tab', { name: 'Order' }), { key: 'ArrowDown' });
    expect(screen.getByRole('tab', { name: 'Billing' }).getAttribute('aria-selected')).toBe('true');
    expect(new URL(window.location.href).searchParams.get('tab')).toBe('billing');

    fireEvent.keyDown(screen.getByRole('tab', { name: 'Billing' }), { key: 'ArrowRight' });
    expect(screen.getByRole('tab', { name: 'Notes' }).getAttribute('aria-selected')).toBe('true');
    expect(new URL(window.location.href).searchParams.get('tab')).toBe('notes');
  });
});
