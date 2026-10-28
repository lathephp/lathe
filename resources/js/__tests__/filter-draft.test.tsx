import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { FilterPanel } from '../index/FilterPanel';
import type { AvailableFilter } from '../index/types';
import type { IndexUrlState } from '../index/useIndexState';

const total: AvailableFilter = {
  key: 'total',
  name: 'Total',
  component: 'range-filter',
  options: null,
  remote: false,
  immediate: false,
  placement: 'panel',
  default: null,
};

afterEach(() => {
  vi.useRealTimers();
});

function panel(state: IndexUrlState, url: string, visit: (changes: IndexUrlState) => void) {
  return <FilterPanel resource="orders" endpoint="/lathe/api/resources/orders/filters" available={[total]} softDeletes={false} state={state} url={url} visit={visit} />;
}

it('keeps the typed range and its focus across the visit the panel sent', async () => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
  const visit = vi.fn();
  const { rerender } = render(panel({}, '/lathe/resources/orders', visit));

  const min = screen.getByLabelText<HTMLInputElement>('Min');
  min.focus();
  fireEvent.change(min, { target: { value: '100' } });

  await act(async () => {
    await vi.advanceTimersByTimeAsync(300);
  });

  expect(visit).toHaveBeenCalledWith({ filter: { total: { min: '100' } } });

  // The person types on while the visit is out.
  fireEvent.change(min, { target: { value: '1005' } });

  // The visit lands with the URL it asked for.
  rerender(panel({ filter: { total: { min: '100' } } }, '/lathe/resources/orders?filter[total][min]=100', visit));

  const after = screen.getByLabelText<HTMLInputElement>('Min');

  expect(after.value).toBe('1005');
  expect(document.activeElement).toBe(after);
});
