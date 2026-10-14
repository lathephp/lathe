import { widget as registerWidget, type WidgetProps } from '@lathephp/core';
import { render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { TableCard } from '../widgets/TableCard';
import type { WidgetDescriptor } from '../widgets/types';
import { WidgetGrid } from '../widgets/WidgetGrid';

afterEach(() => {
  vi.restoreAllMocks();
});

function descriptor(component: string, options: Record<string, unknown> = {}): WidgetDescriptor {
  return {
    name: 'Card',
    uriKey: 'card',
    component,
    width: 'full',
    columns: 12,
    height: 'dynamic',
    help: null,
    helpWidth: 250,
    refreshEvery: 0,
    refreshWhenActionsRun: false,
    refreshWhenFiltersChange: false,
    onlyOnDetail: false,
    requestsValue: false,
    ranges: [],
    ...options,
  };
}

function card(): HTMLElement {
  const found = document.querySelector<HTMLElement>('[data-lathe-widget="card"]');

  if (found === null) {
    throw new Error('No card.');
  }

  return found;
}

const columns = [
  { key: 'name', label: 'Name', align: 'left' },
  { key: 'plan', label: 'Plan', align: 'center' },
  { key: 'total', label: 'Total', align: 'right' },
] as const;

describe('the table card', () => {
  it('aligns each column left, center or right', () => {
    render(<TableCard widget={descriptor('table-widget')} value={{ columns, rows: [{ name: 'Ada', plan: 'Pro', total: 1200 }], emptyText: 'No Results Found...' }} />);

    const cells = within(screen.getByRole('table')).getAllByRole('cell');

    expect(cells.map((cell) => [cell.textContent, cell.className.split(' ').find((name) => name.startsWith('text-'))])).toEqual([
      ['Ada', 'text-start'],
      ['Pro', 'text-center'],
      ['1,200', 'text-end'],
    ]);
  });

  it('says the empty text when there are no rows', () => {
    render(<TableCard widget={descriptor('table-widget')} value={{ columns, rows: [], emptyText: 'No Results Found...' }} />);

    expect(screen.getByText('No Results Found...').tagName).toBe('P');
    expect(screen.queryByRole('table')).toBeNull();
  });

  it('draws a row object with its icon, title, subtitle and links', () => {
    render(
      <TableCard
        widget={descriptor('table-widget')}
        value={{
          columns: [{ key: 'name', label: 'Name' }],
          rows: [{ title: 'Ada', subtitle: 'ada@example.com', icon: 'box', iconClass: 'text-primary', actions: [{ label: 'Open', url: '/lathe/customers/1' }] }],
          emptyText: 'No Results Found...',
        }}
      />,
    );

    const row = within(screen.getByRole('table')).getAllByRole('row')[1];

    expect(row?.querySelector('svg.lucide-box')?.parentElement?.className).toContain('text-primary');
    expect(row?.textContent).toBe('Adaada@example.comOpen');
    expect(within(row as HTMLElement).getByRole('link', { name: 'Open' }).getAttribute('href')).toBe('/lathe/customers/1');
  });

  it('sizes the card to its content', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ name: 'Card', uriKey: 'card', component: 'table-widget', value: { columns, rows: [{ name: 'Ada' }], emptyText: '' } }), { status: 200 }),
    );
    render(<WidgetGrid widgets={[descriptor('table-widget', { requestsValue: true })]} valueUrl="/lathe/api/dashboards/main/widgets" />);

    await screen.findByText('Ada', {}, { timeout: 3000 });

    expect(card().className.split(' ').filter((name) => /^h-/.test(name))).toEqual([]);
  });
});

describe('the help card', () => {
  it('shows the content as plain text with its line breaks, tags left as text', () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    render(<WidgetGrid widgets={[descriptor('help-widget', { content: 'Line 1\nLine 2 <b>x</b>' })]} valueUrl="/lathe/api/dashboards/main/widgets" />);

    const text = within(card()).getByText(/Line 1/);

    expect(text.textContent).toBe('Line 1\nLine 2 <b>x</b>');
    expect(text.querySelector('b')).toBeNull();
    expect(text.className).toContain('whitespace-pre-wrap');
    expect(fetch).not.toHaveBeenCalled();
  });
});

describe('the custom card', () => {
  it('draws the registered client component with the descriptor and makes no value request', () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    registerWidget('sales-goal', ({ widget, value }: WidgetProps) => <p>{`${widget.name} ${String(value)}`}</p>);

    render(<WidgetGrid widgets={[descriptor('custom-widget', { name: 'Goal', clientComponent: 'sales-goal' })]} valueUrl="/lathe/api/dashboards/main/widgets" />);

    expect(within(card()).getByText('Goal null').tagName).toBe('P');
    expect(fetch).not.toHaveBeenCalled();
  });

  it('keeps the error state when no component is registered under its key', () => {
    render(<WidgetGrid widgets={[descriptor('custom-widget', { clientComponent: 'nowhere' })]} valueUrl="/lathe/api/dashboards/main/widgets" />);

    expect(within(card()).getByText('The widget could not be loaded.').tagName).toBe('P');
  });
});
