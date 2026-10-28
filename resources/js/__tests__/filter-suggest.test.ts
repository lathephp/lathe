import { describe, expect, it } from 'vitest';
import { suggest } from '../index/filterTokens';
import type { IndexFilter } from '../index/types';

const __ = (key: string, replace: Record<string, string | number> = {}) =>
  key.replace(/:(\w+)/g, (match, name: string) => (name in replace ? String(replace[name]) : match));

const base = { component: '', options: null, remote: false, immediate: false, placement: 'panel', default: null } as const;

const customer: IndexFilter = { ...base, key: 'customer', name: 'Customer', token: 'customer', kind: 'relation', remote: true };
const total: IndexFilter = { ...base, key: 'field_total_cents', name: 'Total', token: 'total', kind: 'range', scale: 100 };
const featured: IndexFilter = { ...base, key: 'featured', name: 'Featured', token: 'featured', kind: 'boolean', options: [{ label: 'Featured', value: 'featured' }] };
const filters = [customer, total, featured];

describe('filter bar suggestions', () => {
  it('offers Trashed among the filters of a soft-deleting Resource, with its two values', () => {
    expect(suggest('tr', filters, { softDeletes: true }, __)).toEqual({ items: [{ label: 'Trashed', hint: 'trashed:', text: 'trashed:' }] });
    expect(suggest('trashed:', filters, { softDeletes: true }, __)).toEqual({
      items: [
        { label: 'With trashed', chip: { token: 'trashed:with', key: 'trashed', value: 'with', label: 'Trashed: with' } },
        { label: 'Only trashed', chip: { token: 'trashed:only', key: 'trashed', value: 'only', label: 'Trashed: only' } },
      ],
    });
  });

  it('offers Yes and No for a one-option boolean', () => {
    expect(suggest('featured:n', filters, { softDeletes: false }, __)).toEqual({
      items: [{ label: 'No', chip: { token: 'featured:no', key: 'featured', value: { featured: '0' }, label: 'Featured: No' } }],
    });
  });

  it('shows a range\'s grammar in place of values', () => {
    expect(suggest('total:>1', filters, { softDeletes: false }, __)).toEqual({ help: 'Total takes a number, >n, <n or a..b' });
  });

  it('searches a relation for the text after the colon, and only for one value', () => {
    expect(suggest('customer:"Ann T', filters, { softDeletes: false }, __)).toEqual({ lookup: { filter: customer, term: 'Ann T' } });
    expect(suggest('customer:ann,ada', filters, { softDeletes: false }, __)).toEqual({ help: 'Customer takes one value' });
  });
});
