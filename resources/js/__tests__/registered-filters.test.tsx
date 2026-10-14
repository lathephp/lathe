import { filter, type FilterProps } from '@lathephp/core';
import { fireEvent, render, screen } from '@testing-library/react';
import { expect, it, vi } from 'vitest';
import { FilterPanel } from '../index/FilterPanel';
import type { AvailableFilter } from '../index/types';

function Tiers({ filter: definition, onChange }: FilterProps) {
  return (
    <div>
      {(definition.options ?? []).map((option) => (
        <button
          key={option.value}
          type="button"
          onClick={() => {
            onChange(option.value);
          }}
        >
          {option.label}
        </button>
      ))}
    </div>
  );
}

filter('price-tier', Tiers);

const tier: AvailableFilter = {
  key: 'price-tier',
  name: 'Price tier',
  component: 'price-tier',
  options: [
    { label: 'Budget', value: 'budget' },
    { label: 'Premium', value: 'premium' },
  ],
  remote: false,
  immediate: true,
  placement: 'panel',
  default: null,
};

it('draws a filter a plugin registers with core and applies its value', () => {
  const visit = vi.fn();
  render(<FilterPanel resource="products" endpoint="/lathe/api/resources/products/filters" available={[tier]} softDeletes={false} state={{}} visit={visit} />);

  fireEvent.click(screen.getByRole('button', { name: 'Premium' }));

  expect(visit).toHaveBeenCalledWith({ filter: { 'price-tier': 'premium' } });
});
