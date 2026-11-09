import { router } from '@inertiajs/react';
import { emit, queryClient } from '@lathephp/core';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ResourceWidgets } from '../widgets/ResourceWidgets';
import type { WidgetDescriptor } from '../widgets/types';
import { WidgetGrid } from '../widgets/WidgetGrid';
import { choose, renderPage } from './support';

beforeEach(() => {
  queryClient.clear();
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.useRealTimers();
});

function descriptor(uriKey: string, overrides: Partial<WidgetDescriptor> = {}): WidgetDescriptor {
  return {
    name: uriKey.replace(/^./, (first) => first.toUpperCase()),
    uriKey,
    component: 'value-widget',
    width: '1/3',
    columns: 4,
    height: 'fixed',
    help: null,
    helpWidth: 250,
    refreshEvery: 0,
    refreshWhenActionsRun: false,
    refreshWhenFiltersChange: false,
    onlyOnDetail: false,
    requestsValue: true,
    ranges: [],
    ...overrides,
  };
}

function valueOf(uriKey: string, value: number) {
  return {
    name: uriKey,
    uriKey,
    component: 'value-widget',
    value: {
      value,
      previous: null,
      change: null,
      previousLabel: null,
      prefix: null,
      suffix: null,
      suffixInflection: true,
      format: null,
      allowZeroResult: false,
      copyable: false,
    },
  };
}

const dashboard = {
  uriKey: 'main',
  name: 'Sales',
  description: null,
  icon: null,
  showRefreshButton: false,
  ranges: [
    { value: 7, label: '7 days' },
    { value: 30, label: '30 days' },
    { value: 365, label: '12 months' },
  ],
  range: 30,
};

/** Answers every value request with the number the handler picks; the list endpoint answers `list`. */
const urlOf = (input: RequestInfo | URL): string => (typeof input === 'string' ? input : input instanceof URL ? input.href : input.url);

function answer(number: (url: string) => number | Error, list: WidgetDescriptor[] = []) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = urlOf(input);

    if (url.endsWith('/widgets') || url.includes('/widgets?')) {
      return Promise.resolve(new Response(JSON.stringify({ widgets: list }), { status: 200 }));
    }

    const result = number(url);
    const key = /widgets\/([^/?]+)/.exec(url)?.[1] ?? '';

    return Promise.resolve(
      result instanceof Error
        ? new Response(JSON.stringify({ message: result.message }), { status: 500 })
        : new Response(JSON.stringify(valueOf(key, result)), { status: 200 }),
    );
  });
}

function calls(fetch: ReturnType<typeof answer>, part = '/widgets/'): string[] {
  return fetch.mock.calls.map(([input]) => urlOf(input)).filter((url) => url.includes(part));
}

function card(uriKey: string): HTMLElement {
  const found = document.querySelector<HTMLElement>(`[data-lathe-widget="${uriKey}"]`);

  if (found === null) {
    throw new Error(`No card ${uriKey}.`);
  }

  return found;
}

function renderDashboard(widgets: WidgetDescriptor[]) {
  const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);

  renderPage('Dashboard', { dashboard, widgets });

  return { reload };
}

/** The number for this card on this call: each card answers its list in turn, then keeps the last. */
function inTurn(numbers: Record<string, (number | Error)[]>) {
  const asked: Record<string, number> = {};

  return (url: string) => {
    const key = /widgets\/([^/?]+)/.exec(url)?.[1] ?? '';
    const list = numbers[key] ?? [0];
    const at = asked[key] ?? 0;
    asked[key] = at + 1;

    return list[Math.min(at, list.length - 1)] ?? 0;
  };
}

async function pass(ms: number) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms);
  });
}

describe('the dashboard grid', () => {
  it('fills each card in from its own endpoint', async () => {
    // The revenue value is still on its way.
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) =>
      urlOf(input).includes('revenue') ? new Promise(() => undefined) : Promise.resolve(new Response(JSON.stringify(valueOf('orders', 1234)), { status: 200 })),
    );
    renderDashboard([descriptor('orders'), descriptor('revenue')]);

    await waitFor(() => {
      expect(within(card('orders')).getByText('1,234')).toBeTruthy();
    });
    expect(within(card('revenue')).getByRole('status', { name: 'Loading' })).toBeTruthy();
  });

  it('shows a failed card alone, with Try again that loads the value', async () => {
    const fetch = answer(inTurn({ orders: [1234], revenue: [new Error('Server Error'), 42] }));
    renderDashboard([descriptor('orders'), descriptor('revenue')]);

    await waitFor(() => {
      expect(within(card('revenue')).getByText('Revenue could not load')).toBeTruthy();
      expect(within(card('orders')).getByText('1,234')).toBeTruthy();
    });

    fireEvent.click(within(card('revenue')).getByRole('button', { name: 'Try again' }));

    await waitFor(() => {
      expect(within(card('revenue')).getByText('42')).toBeTruthy();
    });
    expect(calls(fetch, 'revenue')).toEqual(['/lathe/api/dashboards/main/widgets/revenue?range=30', '/lathe/api/dashboards/main/widgets/revenue?range=30']);
  });

  it('keeps the empty state of a dashboard with no widgets', () => {
    renderDashboard([]);

    expect(screen.getByText('No widgets yet')).toBeTruthy();
    expect(document.querySelector('[data-lathe-widget]')).toBeNull();
  });

  it('refetches every card from the Refresh button in the header', async () => {
    const fetch = answer(inTurn({ orders: [1, 7], revenue: [2, 8] }));
    renderDashboard([descriptor('orders'), descriptor('revenue')]);

    await waitFor(() => {
      expect(within(card('orders')).getByText('1')).toBeTruthy();
      expect(within(card('revenue')).getByText('2')).toBeTruthy();
    });
    fireEvent.click(screen.getByRole('button', { name: 'Refresh' }));

    await waitFor(() => {
      expect(within(card('orders')).getByText('7')).toBeTruthy();
      expect(within(card('revenue')).getByText('8')).toBeTruthy();
    });
    expect(calls(fetch).sort()).toEqual([
      '/lathe/api/dashboards/main/widgets/orders?range=30',
      '/lathe/api/dashboards/main/widgets/orders?range=30',
      '/lathe/api/dashboards/main/widgets/revenue?range=30',
      '/lathe/api/dashboards/main/widgets/revenue?range=30',
    ]);
  });
});

describe('the dashboard range', () => {
  const ranges = [
    { value: 30, label: '30 Days' },
    { value: 60, label: '60 Days' },
  ];

  it('offers one Range picker in the header and none on the cards', () => {
    answer(() => 1);
    renderDashboard([descriptor('orders', { ranges }), descriptor('revenue')]);

    const picker = screen.getByRole('radiogroup', { name: 'Range' });
    expect(within(picker).getAllByRole('radio').map((radio) => radio.textContent)).toEqual(['7 days', '30 days', '12 months']);
    expect(within(picker).getByRole('radio', { name: '30 days' }).getAttribute('aria-checked')).toBe('true');
    expect(within(picker).getByRole('radio', { name: '7 days' }).getAttribute('aria-checked')).toBe('false');
    expect(screen.queryByRole('combobox', { name: 'Range' })).toBeNull();
  });

  it('names the range a card fell back to', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) =>
      Promise.resolve(
        new Response(
          JSON.stringify(urlOf(input).includes('range=7') ? { ...valueOf('orders', 5), fallbackRange: { value: 30, label: 'Last 30 days' } } : valueOf('orders', 1)),
          { status: 200 },
        ),
      ),
    );
    renderDashboard([descriptor('orders')]);

    await waitFor(() => {
      expect(within(card('orders')).getByText('1')).toBeTruthy();
    });
    fireEvent.click(screen.getByRole('radio', { name: '7 days' }));

    await waitFor(() => {
      expect(within(card('orders')).getByText('5')).toBeTruthy();
    });
    expect(within(card('orders')).getByText('Last 30 days')).toBeTruthy();
  });

  it('keeps the last value on screen, dimmed, while a card reloads', async () => {
    let finish: (response: Response) => void = () => undefined;
    vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(new Response(JSON.stringify(valueOf('orders', 1234)), { status: 200 }))
      .mockReturnValue(
        new Promise((resolve) => {
          finish = resolve;
        }),
      );
    renderDashboard([descriptor('orders')]);

    await waitFor(() => {
      expect(within(card('orders')).getByText('1,234')).toBeTruthy();
    });
    fireEvent.click(screen.getByRole('radio', { name: '7 days' }));

    await waitFor(() => {
      expect(within(card('orders')).getByText('Updating…')).toBeTruthy();
    });
    expect(within(card('orders')).getByText('1,234').closest('.opacity-60')).not.toBeNull();
    expect(within(card('orders')).queryByRole('status', { name: 'Loading' })).toBeNull();

    finish(new Response(JSON.stringify(valueOf('orders', 7)), { status: 200 }));
    await waitFor(() => {
      expect(within(card('orders')).getByText('7')).toBeTruthy();
    });
    expect(within(card('orders')).queryByText('Updating…')).toBeNull();
  });

  it('shows a failed card by name while the others keep their values', async () => {
    answer((url) => (url.includes('range=365') ? (url.includes('orders') ? new Error('Server Error') : 8) : 1));
    renderDashboard([descriptor('orders'), descriptor('revenue')]);

    await waitFor(() => {
      expect(within(card('orders')).getByText('1')).toBeTruthy();
    });
    fireEvent.click(screen.getByRole('radio', { name: '12 months' }));

    await waitFor(() => {
      expect(within(card('orders')).getByText('Orders could not load')).toBeTruthy();
      expect(within(card('revenue')).getByText('8')).toBeTruthy();
    });
    expect(within(card('orders')).getByText('The other widgets are not affected.')).toBeTruthy();
    expect(within(card('orders')).getByRole('button', { name: 'Try again' })).toBeTruthy();
  });
});

describe('refresh triggers', () => {
  it('polls every refreshEvery seconds without an Inertia visit', async () => {
    vi.useFakeTimers();
    const fetch = answer(() => 5);
    const { reload } = renderDashboard([descriptor('orders', { refreshEvery: 5 })]);
    const visit = vi.spyOn(router, 'visit');

    await pass(10_500);

    // The mount's read, then one every 5 s.
    expect(calls(fetch)).toEqual([
      '/lathe/api/dashboards/main/widgets/orders?range=30',
      '/lathe/api/dashboards/main/widgets/orders?range=30',
      '/lathe/api/dashboards/main/widgets/orders?range=30',
    ]);
    expect(visit).not.toHaveBeenCalled();
    expect(reload).not.toHaveBeenCalled();
  });

  it('refetches after actions run only the cards that ask for it', async () => {
    const fetch = answer(inTurn({ orders: [1, 9], revenue: [2, 9] }));
    renderDashboard([descriptor('orders', { refreshWhenActionsRun: true }), descriptor('revenue')]);

    await waitFor(() => {
      expect(within(card('revenue')).getByText('2')).toBeTruthy();
    });
    act(() => {
      emit('actions:ran');
    });

    await waitFor(() => {
      expect(within(card('orders')).getByText('9')).toBeTruthy();
    });
    expect(calls(fetch)).toEqual([
      '/lathe/api/dashboards/main/widgets/orders?range=30',
      '/lathe/api/dashboards/main/widgets/revenue?range=30',
      '/lathe/api/dashboards/main/widgets/orders?range=30',
    ]);
  });

  it('refetches after the filters change only the cards that ask for it', async () => {
    const fetch = answer(inTurn({ orders: [1, 9], revenue: [2, 9] }));
    renderDashboard([descriptor('orders'), descriptor('revenue', { refreshWhenFiltersChange: true })]);

    await waitFor(() => {
      expect(within(card('orders')).getByText('1')).toBeTruthy();
    });
    act(() => {
      emit('filters:changed');
    });

    await waitFor(() => {
      expect(within(card('revenue')).getByText('9')).toBeTruthy();
    });
    expect(calls(fetch)).toEqual([
      '/lathe/api/dashboards/main/widgets/orders?range=30',
      '/lathe/api/dashboards/main/widgets/revenue?range=30',
      '/lathe/api/dashboards/main/widgets/revenue?range=30',
    ]);
  });

  it('refetches only the card whose range changes on a Resource page', async () => {
    const fetch = answer((url) => (url.includes('range=60') ? 60 : 30));
    const ranges = [
      { value: 30, label: '30 Days' },
      { value: 60, label: '60 Days' },
    ];
    render(<WidgetGrid widgets={[descriptor('orders', { ranges }), descriptor('revenue', { ranges })]} valueUrl="/lathe/api/resources/orders/widgets" />);

    await waitFor(() => {
      expect(within(card('revenue')).getByText('30')).toBeTruthy();
    });
    await choose(within(card('orders')).getByRole('combobox', { name: 'Range' }), '60 Days');

    await waitFor(() => {
      expect(within(card('orders')).getByText('60')).toBeTruthy();
    });
    expect(calls(fetch)).toEqual([
      '/lathe/api/resources/orders/widgets/orders?range=30',
      '/lathe/api/resources/orders/widgets/revenue?range=30',
      '/lathe/api/resources/orders/widgets/orders?range=60',
    ]);
    expect(within(card('revenue')).getByText('30')).toBeTruthy();
  });
});

describe('the card', () => {
  it('shows a range selector on a Resource page only when the widget has ranges', () => {
    answer(() => 1);
    render(<WidgetGrid widgets={[descriptor('orders', { ranges: [{ value: 30, label: '30 Days' }] }), descriptor('revenue')]} valueUrl="/lathe/api/resources/orders/widgets" />);

    expect(within(card('orders')).getByRole('combobox', { name: 'Range' })).toBeTruthy();
    expect(within(card('revenue')).queryByRole('combobox')).toBeNull();
  });

  // jsdom does no layout, so this pins the class; at 390 px a browser measured "Cancellations" cut by its range select in a 148 px card.
  it('lets a range select drop below a title too wide for one row beside it', () => {
    answer(() => 1);
    render(<WidgetGrid widgets={[descriptor('orders', { name: 'Cancellations', ranges: [{ value: 30, label: '30 Days' }] })]} valueUrl="/lathe/api/resources/orders/widgets" />);
    const row = within(card('orders')).getByRole('combobox', { name: 'Range' }).closest('div.justify-between');

    expect(row?.className.split(' ')).toContain('flex-wrap');
  });

  it('shows the help text in a tooltip helpWidth px wide', () => {
    answer(() => 1);
    renderDashboard([descriptor('orders', { help: 'Paid orders only.' }), descriptor('revenue', { help: 'Net.', helpWidth: 400 })]);

    const help = within(card('orders')).getByRole('button', { name: 'Help' });
    fireEvent.mouseEnter(help);

    const tooltip = within(card('orders')).getByRole('tooltip');
    expect(tooltip.textContent).toBe('Paid orders only.');
    expect(tooltip.style.width).toBe('250px');
    expect(help.getAttribute('aria-describedby')).toBe(tooltip.id);

    fireEvent.focus(within(card('revenue')).getByRole('button', { name: 'Help' }));
    expect(within(card('revenue')).getByRole('tooltip').style.width).toBe('400px');
  });

  it('shows an unknown renderer as the error state', async () => {
    const fetch = answer(() => 1);
    renderDashboard([descriptor('chart', { component: 'unknown-widget' })]);

    await waitFor(() => {
      expect(within(card('chart')).getByText('Chart could not load')).toBeTruthy();
    });
    // A card with no renderer has nothing to draw a value with, so it asks for none.
    expect(calls(fetch)).toEqual([]);
  });

  // jsdom does no layout, so this pins the classes; a browser check at 1,100 px measured "Orders per day" cut 95>78 beside its dates.
  it('wraps a long title and the dates beside it onto more lines, never cut with an ellipsis', () => {
    answer(() => 1);
    renderDashboard([descriptor('orders-per-day', { name: 'Orders per day', component: 'trend-widget' })]);
    const title = within(card('orders-per-day')).getByRole('heading', { name: 'Orders per day' });
    const meta = card('orders-per-day').querySelector<HTMLElement>('span.ms-auto');

    expect(title.className.split(' ')).not.toContain('truncate');
    expect(title.className.split(' ')).toContain('wrap-break-word');
    expect(meta?.className.split(' ')).not.toContain('truncate');
    expect(meta?.className.split(' ')).toContain('wrap-break-word');
  });

  it('lays cards out on 12 columns with a 20 px gap, value cards of a third or less two across in a grid under 768 px', () => {
    answer(() => 1);
    renderDashboard([3, 4, 6, 8, 9, 12].map((columns) => descriptor(`w${String(columns)}`, { columns })));

    // The grid's own width picks the layout, so a sidebar beside a 768 px window still gets the phone's.
    const grid = card('w3').parentElement?.className.split(' ');
    expect(grid).toContain('@container');
    expect(grid).toContain('grid-cols-12');
    expect(grid).toContain('md:gap-5');

    for (const columns of [3, 4, 6, 8, 9, 12]) {
      const classes = card(`w${String(columns)}`).className.split(' ');
      expect(classes).toContain(columns <= 4 ? 'col-span-6' : 'col-span-12');
      expect(classes).toContain(`@3xl:col-span-${String(columns)}`);
      expect(classes.filter((name) => /^md:(col-span|min-h)-/.test(name))).toEqual([]);
    }
  });

  it('is at least 160 px high, 320 px when double and sized to content when dynamic', () => {
    answer(() => 1);
    renderDashboard([descriptor('fixed'), descriptor('double', { height: 'double' }), descriptor('dynamic', { height: 'dynamic' })]);

    expect(card('fixed').className.split(' ')).toContain('@3xl:min-h-40');
    expect(card('double').className.split(' ')).toContain('@3xl:min-h-80');
    expect(card('dynamic').className).not.toMatch(/\bh-(40|80)\b/);
  });
});

describe('cards that fetch their own value', () => {
  it('holds the skeleton for at least 300 ms', async () => {
    vi.useFakeTimers();
    answer(() => 5);
    render(<WidgetGrid widgets={[descriptor('orders')]} valueUrl="/lathe/api/resources/orders/widgets" />);

    await pass(299);
    expect(within(card('orders')).getByRole('status', { name: 'Loading' })).toBeTruthy();

    await pass(1);
    expect(within(card('orders')).queryByRole('status')).toBeNull();
    expect(within(card('orders')).getByText('5')).toBeTruthy();
  });

  it('shows a failed request with Try again, and Try again loads the value', async () => {
    let fail = true;
    const fetch = answer(() => (fail ? new Error('Server Error') : 11));
    render(<WidgetGrid widgets={[descriptor('orders')]} valueUrl="/lathe/api/resources/orders/widgets" params={{ resourceId: '12' }} />);

    await waitFor(() => {
      expect(within(card('orders')).getByText('Orders could not load')).toBeTruthy();
    });

    fail = false;
    fireEvent.click(within(card('orders')).getByRole('button', { name: 'Try again' }));

    await waitFor(() => {
      expect(within(card('orders')).getByText('11')).toBeTruthy();
    });
    expect(calls(fetch)).toEqual(['/lathe/api/resources/orders/widgets/orders?resourceId=12', '/lathe/api/resources/orders/widgets/orders?resourceId=12']);
  });
});

describe('the Resource mounts', () => {
  it.each([
    ['an index', 'resources/orders', undefined, '/lathe/api/resources/orders/widgets/orders'],
    ['a lens', 'resources/orders/lenses/big', undefined, '/lathe/api/resources/orders/lenses/big/widgets/orders'],
    ['a detail page', 'resources/orders', '12', '/lathe/api/resources/orders/widgets/orders?resourceId=12'],
  ])('fills the listed cards of %s, asking only for their values', async (_, path, resourceId, value) => {
    const fetch = answer(() => 3);
    render(<ResourceWidgets widgets={[descriptor('orders')]} path={path} resourceId={resourceId} />);

    await waitFor(() => {
      expect(within(card('orders')).getByText('3')).toBeTruthy();
    });
    expect(fetch.mock.calls.map(([input]) => urlOf(input))).toEqual([value]);
  });

  it('renders nothing and asks for nothing when the list is empty', () => {
    const fetch = answer(() => 3);
    const { container } = render(<ResourceWidgets widgets={[]} path="resources/orders" />);

    expect(container.innerHTML).toBe('');
    expect(fetch).not.toHaveBeenCalled();
  });
});
