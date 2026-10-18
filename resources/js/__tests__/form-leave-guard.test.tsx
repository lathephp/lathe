import { router } from '@inertiajs/react';
import { act, fireEvent, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { FieldSchema } from '../fields/types';
import { fakeInertiaServer, renderPage, type FakeRequest } from './support';

const question = 'Leave this page? Your unsaved changes will be lost.';

const navigation = [
  { label: null, icon: null, items: [{ label: 'Dashboard', href: '/lathe', icon: null, component: 'Dashboard' }] },
  {
    label: 'Resources',
    icon: null,
    items: [{ label: 'Orders', href: '/lathe/resources/orders', icon: null, component: 'Resources/Index', cacheTag: 'resource:orders', skeleton: { tabs: false, actions: true } }],
  },
];

const resource = { uriKey: 'customers', label: 'Customers', singularLabel: 'Customer', abilities: { create: true } };

function schema(key: string): FieldSchema {
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
  };
}

const panel = {
  type: 'panel' as const,
  attribute: 'details',
  name: 'Details',
  fields: ['name'],
  toolbar: true,
  collapsible: false,
  collapsed: false,
  hideHeading: false,
  headingInCard: false,
  help: null,
  limit: null,
  columns: null,
  columnSpan: null,
  borderless: false,
};

let requests: FakeRequest[];

beforeEach(() => {
  requests = fakeInertiaServer();
  router.flushAll();
  vi.useFakeTimers();
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
  window.sessionStorage.clear();
});

function renderForm() {
  renderPage(
    'Resources/Form',
    {
      resource,
      mode: 'create',
      id: null,
      title: 'Create Customer',
      breadcrumbs: [],
      form: { fields: [{ ...schema('name'), value: '' }], layout: [panel] },
      submitLabel: 'Create Customer',
    },
    { url: '/lathe/resources/customers/new', shared: { navigation } },
  );
}

function type(value: string) {
  fireEvent.change(screen.getByLabelText('Name'), { target: { value } });
}

function clickOrders() {
  fireEvent.click(within(screen.getByRole('complementary')).getByRole('link', { name: 'Orders' }));
}

describe('leaving a form with unsaved changes', () => {
  it('asks first, and a No keeps the form and what was typed', async () => {
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
    renderForm();
    type('Bea');

    clickOrders();
    await act(() => vi.advanceTimersByTimeAsync(150));

    expect(confirm).toHaveBeenCalledWith(question);
    expect(requests).toHaveLength(0);
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Create Customer');
    expect(screen.getByLabelText<HTMLInputElement>('Name').value).toBe('Bea');
  });

  it('goes on with the visit after a Yes', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    renderForm();
    type('Bea');

    clickOrders();
    await act(() => vi.advanceTimersByTimeAsync(150));

    expect(requests).toHaveLength(1);
    expect(requests[0]?.url).toContain('/lathe/resources/orders');
  });

  it('does not ask when nothing changed', async () => {
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
    renderForm();

    clickOrders();
    await act(() => vi.advanceTimersByTimeAsync(150));

    expect(confirm).not.toHaveBeenCalled();
    expect(requests).toHaveLength(1);
  });

  it('does not ask on the redirect after its own successful create', async () => {
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ id: 9, redirect: 'customers/9' }), { status: 201, headers: { 'Content-Type': 'application/json' } }),
    );
    renderForm();
    type('Bea');

    fireEvent.click(screen.getByRole('button', { name: 'Create Customer' }));
    await act(() => vi.advanceTimersByTimeAsync(50));

    expect(confirm).not.toHaveBeenCalled();
    expect(requests).toHaveLength(1);
    expect(requests[0]?.url).toContain('/lathe/resources/customers/9');
  });
});
