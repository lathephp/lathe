import { __panel } from '@lathephp/core';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ProgressCard } from '../widgets/ProgressCard';
import type { WidgetDescriptor } from '../widgets/types';
import { ValueCard } from '../widgets/ValueCard';

const widget: WidgetDescriptor = {
  name: 'Orders',
  uriKey: 'orders',
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
};

/** A ValueResult as the server serializes it. */
function valueResult(overrides: Record<string, unknown> = {}) {
  return {
    value: 0,
    previous: null,
    change: null,
    previousLabel: null,
    prefix: null,
    suffix: null,
    suffixInflection: true,
    format: null,
    tooltipFormat: null,
    allowZeroResult: false,
    copyable: false,
    precision: null,
    roundingMode: null,
    ...overrides,
  };
}

function showValue(overrides: Record<string, unknown>) {
  render(<ValueCard widget={widget} value={valueResult(overrides)} />);
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('value display rules', () => {
  it.each([
    [9999.5, '9,999.5'],
    [9999.456, '9,999.46'],
    [12345, '12.3K'],
    [1234567, '1.2M'],
    [1234567890, '1.2B'],
    [null, '—'],
  ])('shows %s as %s', (value, shown) => {
    showValue({ value });

    expect(screen.getByText(shown)).toBeTruthy();
  });

  it('shows a value of 0 as No Data, and as 0 when zero is allowed', () => {
    showValue({ value: 0 });
    expect(screen.getByText('No Data')).toBeTruthy();

    showValue({ value: 0, allowZeroResult: true });
    expect(screen.getByText('0')).toBeTruthy();
  });

  it('uses the shared locale', () => {
    __panel.sharedProps.set({ locale: 'de' });
    showValue({ value: 1234.5 });

    expect(screen.getByText('1.234,5')).toBeTruthy();
  });

  it.each([
    [1, 'order', true, '1 order'],
    [2, 'order', true, '2 orders'],
    [-1, 'order', true, '-1 order'],
    [2, 'sales', true, '2 sales'],
    [2, 'order', false, '2 order'],
  ])('shows %s with the suffix %s (inflection %s) as %s', (value, suffix, suffixInflection, shown) => {
    showValue({ value, suffix, suffixInflection });

    expect(screen.getByText(shown)).toBeTruthy();
  });

  it.each([
    ['$', '$1,234'],
    ['€', '€1,234'],
    ['£', '£1,234'],
  ])('puts the prefix %s before the number', (prefix, shown) => {
    showValue({ value: 1234, prefix });

    expect(screen.getByText(shown)).toBeTruthy();
  });

  it.each([
    ['a', 1234567, '1.2M'],
    ['.2', 1234.5, '1,234.50'],
    [{ style: 'percent' }, 0.25, '25%'],
  ])('formats with %j', (format, value, shown) => {
    showValue({ value, format });

    expect(screen.getByText(shown)).toBeTruthy();
  });
});

describe('the change badge', () => {
  it.each([
    [50, 'up', '+50.0%'],
    [-10, 'down', '-10.0%'],
    [0, 'flat', '0.0%'],
  ])('shows %s as a %s arrow', (change, direction, shown) => {
    showValue({ value: 150, previous: 100, change });

    const badge = screen.getByText(shown).closest('[data-change]');
    expect(badge?.getAttribute('data-change')).toBe(direction);
    expect(badge?.className).toContain(direction === 'up' ? 'text-success' : direction === 'down' ? 'text-destructive' : 'text-muted-foreground');
  });

  it('shows no badge for a null change and never NaN or Infinity', () => {
    const { container } = render(<ValueCard widget={widget} value={valueResult({ value: 5, previous: 0, change: null })} />);

    expect(container.querySelector('[data-change]')).toBeNull();
    expect(container.textContent).not.toMatch(/NaN|Infinity/);
  });

  it('shows no badge for a bare number from calculate()', () => {
    const { container } = render(<ValueCard widget={widget} value={42} />);

    expect(container.querySelector('[data-change]')).toBeNull();
    expect(container.textContent).not.toContain('NaN');
  });

  it('shows the previous value under its label', () => {
    showValue({ value: 150, previous: 100, change: 50, previousLabel: 'last month' });

    expect(screen.getByText('last month: 100')).toBeTruthy();
  });
});

describe('the copy button', () => {
  it('copies the number and says so', async () => {
    const writeText = vi.fn(() => Promise.resolve());
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
    showValue({ value: 1234, prefix: '$', copyable: true });

    fireEvent.click(screen.getByRole('button', { name: 'Copy' }));

    await waitFor(() => {
      expect(__panel.toasts.get().map((toast) => toast.message)).toEqual(['Copied to clipboard']);
    });
    expect(writeText).toHaveBeenCalledWith('$1,234');
  });

  it('has no copy button unless copyable', () => {
    showValue({ value: 1234 });

    expect(screen.queryByRole('button', { name: 'Copy' })).toBeNull();
  });
});

describe('the final number', () => {
  const progressValue = { value: 1581, target: 2000, percentage: 79, avoid: false, color: 'success', caption: 'of target', prefix: null, suffix: null, suffixInflection: true, format: null };

  function allowMotion() {
    vi.useFakeTimers();
    vi.stubGlobal('matchMedia', () => ({ matches: false }));
  }

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
  });

  it('shows a value card number on the first render and never another', () => {
    allowMotion();
    const frame = vi.spyOn(globalThis, 'requestAnimationFrame');
    showValue({ value: 1581 });

    expect(screen.getByText('1,581')).toBeTruthy();
    act(() => {
      vi.advanceTimersByTime(1000);
    });
    expect(screen.getByText('1,581')).toBeTruthy();
    expect(frame).not.toHaveBeenCalled();
  });

  it('shows a progress card number on the first render and never another', () => {
    allowMotion();
    const frame = vi.spyOn(globalThis, 'requestAnimationFrame');
    render(<ProgressCard widget={{ ...widget, component: 'progress-widget' }} value={progressValue} />);

    expect(screen.getByText('1,581')).toBeTruthy();
    act(() => {
      vi.advanceTimersByTime(1000);
    });
    expect(screen.getByText('1,581')).toBeTruthy();
    expect(frame).not.toHaveBeenCalled();
  });
});

describe('the progress card', () => {
  function progress(overrides: Record<string, unknown> = {}) {
    return {
      value: 75,
      target: 100,
      percentage: 75,
      avoid: false,
      color: 'success',
      caption: 'of target',
      prefix: null,
      suffix: null,
      suffixInflection: true,
      format: null,
      ...overrides,
    };
  }

  it.each([
    ['success', 'bg-success'],
    ['warning', 'bg-warning'],
    ['destructive', 'bg-destructive'],
  ])('shows a %s bar and the percentage', (color, token) => {
    render(<ProgressCard widget={{ ...widget, component: 'progress-widget' }} value={progress({ color })} />);

    const bar = screen.getByRole('progressbar');
    expect(bar.getAttribute('aria-valuenow')).toBe('75');
    expect(bar.querySelector(`.${token}`)).not.toBeNull();
    expect(screen.getByText('75%')).toBeTruthy();
    expect(screen.getByText('of target')).toBeTruthy();
  });

  it('shows the number of its target, then the share with the caption', () => {
    render(<ProgressCard widget={{ ...widget, component: 'progress-widget' }} value={progress({ value: 400, target: 1581, percentage: 25.3 })} />);

    expect(screen.getByText('400')).toBeTruthy();
    expect(screen.getByText('of 1,581')).toBeTruthy();
    expect(screen.getByText('25.3%')).toBeTruthy();
  });

  it('shows the caption the server sends for a number to keep low', () => {
    render(<ProgressCard widget={{ ...widget, component: 'progress-widget' }} value={progress({ avoid: true, caption: 'Lower is better', prefix: '$', value: 750, target: 1000 })} />);

    expect(screen.getByText('Lower is better')).toBeTruthy();
    expect(screen.getByText('$750')).toBeTruthy();
  });
});
