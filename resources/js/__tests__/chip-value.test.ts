import { describe, expect, it } from 'vitest';
import { chipValue } from '../index/FilterChips';
import type { AvailableFilter } from '../index/types';

const __ = (key: string) => key;
const base = { remote: false, immediate: false, placement: 'panel', default: null, scale: undefined };

describe('a chip label read back from the URL', () => {
  it('names an option whose value the server sent as a number', () => {
    const author = { ...base, key: 'field_author', name: 'Author', component: 'select-filter', options: [{ label: 'Ann', value: 3 }] } as unknown as AvailableFilter;

    expect(chipValue('3', author, undefined, __)).toBe('Ann');
  });

  it('reads a one-option boolean as Yes or No, and a several-option one as the options turned on', () => {
    const featured: AvailableFilter = { ...base, key: 'featured', name: 'Featured', component: 'boolean-filter', options: [{ label: 'Featured', value: 'featured' }] };
    const flags: AvailableFilter = {
      ...base,
      key: 'flags',
      name: 'Flags',
      component: 'boolean-filter',
      options: [
        { label: 'Gift', value: 'gift' },
        { label: 'Rush', value: 'rush' },
      ],
    };

    expect(chipValue({ featured: '1' }, featured, undefined, __)).toBe('Yes');
    expect(chipValue({ featured: '0' }, featured, undefined, __)).toBe('No');
    expect(chipValue({ gift: '1', rush: '0' }, flags, undefined, __)).toBe('Gift');
  });
});
