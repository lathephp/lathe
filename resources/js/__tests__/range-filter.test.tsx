import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { RangeFilter } from '../index/filters/RangeFilter';
import type { AvailableFilter } from '../index/types';

const total: AvailableFilter = {
  key: 'field_total_cents',
  name: 'Total',
  component: 'range-filter',
  options: null,
  remote: false,
  immediate: false,
  placement: 'panel',
  default: null,
  scale: 100,
};

describe('range filter', () => {
  it('shows and takes the amount the column shows, and sends the stored cents', () => {
    const onChange = vi.fn();
    render(<RangeFilter filter={total} value={{ min: '240050' }} onChange={onChange} endpoint="" resource="orders" />);

    expect(screen.getByLabelText<HTMLInputElement>('Min').value).toBe('2400.5');

    fireEvent.change(screen.getByLabelText('Max'), { target: { value: '2500.25' } });

    expect(onChange).toHaveBeenLastCalledWith({ min: '240050', max: '250025' });
  });

  it('sends the typed value as it is without a scale', () => {
    const onChange = vi.fn();
    render(<RangeFilter filter={{ ...total, scale: 1 }} value={{}} onChange={onChange} endpoint="" resource="orders" />);

    fireEvent.change(screen.getByLabelText('Min'), { target: { value: '7' } });

    expect(onChange).toHaveBeenLastCalledWith({ min: '7' });
  });
});
