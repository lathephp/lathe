import type { SafeHtml } from '@lathephp/core';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LayoutBlock, PanelBlock, TabGroupBlock } from '../fields/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  window.history.replaceState(null, '', '/');
});

const resource = { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: true } };

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

function renderDetail(layout: LayoutBlock[], keys: string[]) {
  return renderPage('Resources/Detail', {
    resource,
    record: { id: 12, title: 'ORD-00012', subtitle: '', avatar: null, fields: keys.map((key) => field(key)), layout, abilities: { view: true, update: false, delete: false, replicate: false, restore: false, forceDelete: false }, trashed: false },
    breadcrumbs: [],
  });
}

function renderForm(layout: LayoutBlock[], keys: string[], borderless = false) {
  return renderPage('Resources/Form', {
    resource,
    mode: 'update',
    id: 12,
    title: 'ORD-00012',
    breadcrumbs: [],
    form: { fields: keys.map((key) => field(key)), layout, updatedAt: '2026-10-14T09:00:00.000Z', borderless },
    submitLabel: 'Update Order',
  });
}

describe('relation blocks', () => {
  it('renders nothing for a relation-field block and still draws the panels around it', () => {
    const relation = { type: 'relation-field', key: 'items', field: field('items') } as LayoutBlock;

    renderDetail([panel('order', ['gadget']), relation, { ...relation, type: 'has-one-warranty' } as LayoutBlock], ['gadget']);

    expect(screen.getByText('Gadget')).toBeTruthy();
    expect(screen.queryByText('Items')).toBeNull();
  });
});

describe('panels', () => {
  it('shows limit(3) fields on detail and Show all for the rest', () => {
    renderDetail([panel('order', ['a', 'b', 'c', 'd', 'e'], { limit: 3 })], ['a', 'b', 'c', 'd', 'e']);

    expect(screen.queryByText('D')).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: 'Show all' }));
    expect(screen.getByText('D')).toBeDefined();
  });

  it('starts collapsed with collapsedByDefault and opens on its toggle', () => {
    renderDetail([panel('order', ['a'], { collapsible: true, collapsed: true })], ['a']);
    const toggle = screen.getByRole('button', { name: 'Order' });

    expect(toggle.getAttribute('aria-expanded')).toBe('false');
    fireEvent.click(toggle);
    expect(toggle.getAttribute('aria-expanded')).toBe('true');
  });

  it('collapsible_toggle_does_not_submit_form', () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    renderForm([panel('order', ['a'], { collapsible: true })], ['a']);

    fireEvent.click(screen.getByRole('button', { name: 'Order' }));

    expect(fetch).not.toHaveBeenCalled();
  });

  it('renders Section help through SafeHtml', () => {
    const { container } = renderDetail([panel('order', ['a'], { help: { __html: '<b>Read me</b>' } as SafeHtml })], ['a']);

    expect(container.querySelector('b')?.textContent).toBe('Read me');
  });

  it('drops the divider lines of a borderless Resource, Section and Tab', () => {
    const { container, unmount } = renderForm([panel('order', ['a', 'b'], { borderless: true })], ['a', 'b']);
    expect(container.querySelector('[id="panel-order"]')?.className).toContain('divide-y-0');
    unmount();

    const resourceWide = renderForm([panel('order', ['a', 'b'])], ['a', 'b'], true);
    expect(resourceWide.container.querySelector('[id="panel-order"]')?.className).toContain('divide-y-0');
    resourceWide.unmount();

    const group = tabs('more', 'tab', ['billing']);
    const tab = group.tabs[0];
    if (tab !== undefined) tab.borderless = true;
    const tabbed = renderForm([group], ['billing']);
    expect(tabbed.container.querySelector('[id="panel-billing-fields"]')?.className).toContain('divide-y-0');
  });
});

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

  it('shows a tab badge with its type colour', () => {
    const group = tabs('main', 'tab', ['order', 'billing']);
    const tab = group.tabs[1];
    if (tab !== undefined) {
      tab.badge = '3';
      tab.badgeType = 'danger';
    }
    renderDetail([group], ['order', 'billing']);

    expect(within(screen.getByRole('tab', { name: /Billing/ })).getByText('3').className).toContain('bg-red-100');
  });

  it('marks the tab of a failed field with a red dot and opens it', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ message: 'The billing field is required.', errors: { billing: ['The billing field is required.'] } }), { status: 422 }),
    );
    renderForm([tabs('main', 'tab', ['order', 'billing'])], ['order', 'billing']);

    fireEvent.click(screen.getByRole('button', { name: 'Update Order' }));

    await waitFor(() => {
      expect(screen.getByRole('img', { name: 'Has errors' })).toBeDefined();
    });
    const billing = screen.getByRole('tab', { name: /Billing/ });
    expect(billing.getAttribute('aria-selected')).toBe('true');
    expect(within(billing).getByRole('img', { name: 'Has errors' })).toBeDefined();
  });
});
