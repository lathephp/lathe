import { act, fireEvent, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderPage } from './support';

function strip() {
  renderPage(
    'Dashboard',
    {
      dashboard: {
        uriKey: 'main',
        name: 'Main',
        description: null,
        customizable: false,
        customized: false,
        ranges: [
          { value: 7, label: '7 days' },
          { value: 30, label: '30 days' },
          { value: 365, label: '12 months' },
        ],
        range: 7,
      },
      widgets: [
        {
          name: 'Orders',
          uriKey: 'orders',
          component: 'value-widget',
          width: '1/2',
          columns: 6,
          height: 'fixed',
          help: null,
          helpWidth: 250,
          refreshEvery: 0,
          refreshWhenActionsRun: false,
          refreshWhenFiltersChange: false,
          onlyOnDetail: false,
          requestsValue: true,
          ranges: [],
        },
      ],
      'widgetValue:orders': { value: 1 },
    },
    {},
  );

  return screen.getByRole('radiogroup', { name: 'Range' });
}

function scrollTo(group: HTMLElement, left: number, width: number, visible: number) {
  Object.defineProperty(group, 'scrollWidth', { configurable: true, value: width });
  Object.defineProperty(group, 'clientWidth', { configurable: true, value: visible });
  Object.defineProperty(group, 'scrollLeft', { configurable: true, value: left });
  act(() => {
    fireEvent.scroll(group);
  });
}

describe('the dashboard range strip on a phone', () => {
  it('fades the end edge while there is more to scroll, and the start edge once scrolled', () => {
    const group = strip();

    scrollTo(group, 0, 400, 300);
    expect(group.hasAttribute('data-fade-end')).toBe(true);
    expect(group.hasAttribute('data-fade-start')).toBe(false);
    expect(group.className).toContain('data-fade-end:[--fade-end:0]');

    scrollTo(group, 100, 400, 300);
    expect(group.hasAttribute('data-fade-start')).toBe(true);
    expect(group.hasAttribute('data-fade-end')).toBe(false);
    expect(group.className).toContain('data-fade-start:[--fade-start:0]');
  });

  it('fades neither edge when every option fits', () => {
    const group = strip();

    scrollTo(group, 0, 300, 300);

    expect(group.hasAttribute('data-fade-start')).toBe(false);
    expect(group.hasAttribute('data-fade-end')).toBe(false);
  });
});
