import { describe, expect, it } from 'vitest';
import { chipFromResults, parseToken, splitInput, suggest, toUrlState, type FilterChip } from '../index/filterTokens';
import type { IndexFilter } from '../index/types';

const __ = (key: string, replace: Record<string, string | number> = {}) =>
  key.replace(/:(\w+)/g, (match, name: string) => (name in replace ? String(replace[name]) : match));

const base = { component: '', options: null, remote: false, immediate: false, placement: 'panel', default: null } as const;

const status: IndexFilter = {
  ...base,
  key: 'status',
  name: 'Status',
  token: 'status',
  kind: 'multiple',
  component: 'multi-select-filter',
  options: [
    { label: 'Pending', value: 'pending' },
    { label: 'Paid', value: 'paid' },
    { label: 'Shipped', value: 'shipped' },
    { label: 'Cancelled', value: 'cancelled' },
  ],
};
const customer: IndexFilter = { ...base, key: 'customer', name: 'Customer', token: 'customer', kind: 'relation', remote: true };
const total: IndexFilter = {
  ...base,
  key: 'field_total_cents',
  name: 'Total',
  token: 'total',
  kind: 'range',
  component: 'range-filter',
  scale: 100,
};
const created: IndexFilter = { ...base, key: 'created_at', name: 'Created', token: 'created', kind: 'date', component: 'date-filter' };
const featured: IndexFilter = {
  ...base,
  key: 'featured',
  name: 'Featured',
  token: 'featured',
  kind: 'boolean',
  component: 'boolean-filter',
  options: [{ label: 'Featured', value: 'featured' }],
};
const flags: IndexFilter = {
  ...base,
  key: 'flags',
  name: 'Flags',
  token: 'flag',
  kind: 'boolean',
  component: 'boolean-filter',
  options: [
    { label: 'Gift', value: 'gift' },
    { label: 'Rush', value: 'rush' },
  ],
};
const sku: IndexFilter = { ...base, key: 'sku', name: 'SKU', token: 'sku', kind: 'text' };
const size: IndexFilter = {
  ...base,
  key: 'size',
  name: 'Size',
  token: 'size',
  kind: 'select',
  options: [
    { label: 'Small', value: 's' },
    { label: 'Large', value: 'l' },
  ],
};

const filters = [status, customer, total, created, featured, flags, sku, size];

function chipOf(token: string, softDeletes = false): FilterChip {
  const parsed = parseToken(token, filters, { softDeletes }, __);

  if (!('chip' in parsed)) {
    throw new Error('expected a chip');
  }

  return parsed.chip;
}

describe('splitInput', () => {
  it('splits key:value tokens from plain words, keeping quoted phrases in the words', () => {
    expect(splitInput('ann status:paid  big "x y"')).toEqual({ tokens: ['status:paid'], words: 'ann big "x y"' });
  });

  it('keeps a quoted value with its token', () => {
    expect(splitInput('customer:"Ann Taylor","Ada Quinn" late')).toEqual({
      tokens: ['customer:"Ann Taylor","Ada Quinn"'],
      words: 'late',
    });
  });
});

describe('select and multiple filters', () => {
  it('reads an option by value and writes the scalar', () => {
    const chip = chipOf('status:pending');

    expect(chip.label).toBe('Status: Pending');
    expect(toUrlState([chip])).toEqual({ filter: { status: 'pending' } });
  });

  it('ignores case in the key and the value, and matches labels', () => {
    expect(toUrlState([chipOf('STATUS:Pending')])).toEqual({ filter: { status: 'pending' } });
    expect(toUrlState([chipOf('size:LARGE')])).toEqual({ filter: { size: 'l' } });
  });

  it('writes a list for two values', () => {
    const chip = chipOf('status:pending,paid');

    expect(chip.label).toBe('Status: Pending, Paid');
    expect(toUrlState([chip])).toEqual({ filter: { status: ['pending', 'paid'] } });
  });

  it('refuses an option the filter lacks', () => {
    expect(chipOf('status:lost').error).toBe('Status has no option lost');
  });

  it('refuses a list on a select filter', () => {
    expect(chipOf('size:s,l').error).toBe('Size takes one value');
  });
});

describe('range filters', () => {
  it('writes stored units for a minimum', () => {
    const chip = chipOf('total:>1500');

    expect(chip.label).toBe('Total: ≥ 1,500');
    expect(toUrlState([chip])).toEqual({ filter: { field_total_cents: { min: '150000' } } });
  });

  it('writes a maximum', () => {
    expect(toUrlState([chipOf('total:<20')])).toEqual({ filter: { field_total_cents: { max: '2000' } } });
  });

  it('writes both sides of a span', () => {
    const chip = chipOf('total:10..50');

    expect(chip.label).toBe('Total: 10 – 50');
    expect(toUrlState([chip])).toEqual({ filter: { field_total_cents: { min: '1000', max: '5000' } } });
  });

  it('takes a bare number as both sides', () => {
    expect(toUrlState([chipOf('total:15.5')])).toEqual({ filter: { field_total_cents: { min: '1550', max: '1550' } } });
  });

  it('shows a bad number as a red chip that is not applied', () => {
    const chip = chipOf('total:lots');

    expect(chip.error).toBe('Total takes a number, >n, <n or a..b');
    expect(toUrlState([chip])).toEqual({ filter: {} });
  });
});

describe('date filters', () => {
  it('takes a day as both sides', () => {
    const chip = chipOf('created:2026-03-05');

    expect(chip.label).toBe('Created: 2026-03-05');
    expect(toUrlState([chip])).toEqual({ filter: { created_at: { from: '2026-03-05', to: '2026-03-05' } } });
  });

  it('takes >, < and a span', () => {
    expect(toUrlState([chipOf('created:>2026-03-05')])).toEqual({ filter: { created_at: { from: '2026-03-05' } } });
    expect(toUrlState([chipOf('created:<2026-03-05')])).toEqual({ filter: { created_at: { to: '2026-03-05' } } });
    expect(chipOf('created:2026-01-01..2026-01-31').label).toBe('Created: 2026-01-01 – 2026-01-31');
    expect(toUrlState([chipOf('created:2026-01-01..2026-01-31')])).toEqual({
      filter: { created_at: { from: '2026-01-01', to: '2026-01-31' } },
    });
  });

  it('refuses a day that is not on the calendar', () => {
    expect(chipOf('created:2026-02-30').error).toBe('Created takes a date as YYYY-MM-DD, >date, <date or a..b');
  });
});

describe('boolean filters', () => {
  it('takes yes or no on a one-option filter', () => {
    expect(chipOf('featured:yes').label).toBe('Featured: Yes');
    expect(toUrlState([chipOf('featured:YES')])).toEqual({ filter: { featured: { featured: '1' } } });
    expect(toUrlState([chipOf('featured:no')])).toEqual({ filter: { featured: { featured: '0' } } });
    expect(chipOf('featured:maybe').error).toBe('Featured takes yes or no');
  });

  it('takes option values or labels on a several-option filter', () => {
    const chip = chipOf('flag:Gift,rush');

    expect(chip.label).toBe('Flags: Gift, Rush');
    expect(toUrlState([chip])).toEqual({ filter: { flags: ['gift', 'rush'] } });
    expect(chipOf('flag:red').error).toBe('Flags has no option red');
  });
});

describe('text filters', () => {
  it('passes the text as the one value', () => {
    const chip = chipOf('sku:"AB 12,3"');

    expect(chip.label).toBe('SKU: AB 12,3');
    expect(toUrlState([chip])).toEqual({ filter: { sku: 'AB 12,3' } });
  });
});

describe('relation filters', () => {
  it('asks for a lookup instead of a chip', () => {
    expect(parseToken('customer:"Ann Taylor"', filters, { softDeletes: false }, __)).toEqual({
      lookup: { filter: customer, term: 'Ann Taylor' },
    });
  });

  it('prefers an exact label over the first result', () => {
    const chip = chipFromResults(
      customer,
      'Ann Taylor',
      [
        { label: 'Ann Taylorson', value: 7 },
        { label: 'ann taylor', value: 12 },
      ],
      __,
    );

    expect(chip.label).toBe('Customer: ann taylor');
    expect(toUrlState([chip])).toEqual({ filter: { customer: '12' } });
  });

  it('falls back to the first result', () => {
    expect(chipFromResults(customer, 'Ann', [{ label: 'Ann Taylorson', value: 7 }], __).value).toBe('7');
  });

  it('shows no result as a red chip', () => {
    const chip = chipFromResults(customer, 'Ann Taylor', [], __);

    expect(chip.error).toBe('No Customer matches Ann Taylor');
    expect(toUrlState([chip])).toEqual({ filter: {} });
  });

  it('refuses a list', () => {
    expect(chipOf('customer:"Ann Taylor","Ada Quinn"').error).toBe('Customer takes one value');
  });
});

describe('trashed and unknown keys', () => {
  it('maps trashed to the URL param when the Resource soft-deletes', () => {
    const chip = chipOf('trashed:Only', true);

    expect(chip.label).toBe('Trashed: only');
    expect(toUrlState([chip])).toEqual({ filter: {}, trashed: 'only' });
    expect(chipOf('trashed:with', true).value).toBe('with');
  });

  it('refuses any other trashed value', () => {
    expect(chipOf('trashed:all', true).error).toBe('Trashed takes with or only');
  });

  it('leaves trashed unknown when the Resource keeps nothing deleted', () => {
    expect(chipOf('trashed:with').error).toBe('No filter named trashed');
  });

  it('leaves trashed to a real filter that uses the token', () => {
    const own: IndexFilter = { ...sku, key: 'own', token: 'trashed' };
    const parsed = parseToken('trashed:x', [own], { softDeletes: true }, __);

    expect('chip' in parsed && parsed.chip.key).toBe('own');
  });

  it('shows an unknown key as a red chip that is not applied', () => {
    const chip = chipOf('statuz:paid');

    expect(chip.error).toBe('No filter named statuz');
    expect(chip.token).toBe('statuz:paid');
    expect(toUrlState([chip])).toEqual({ filter: {} });
  });
});

describe('toUrlState', () => {
  it('lets a later chip replace an earlier one for the same key', () => {
    expect(toUrlState([chipOf('status:paid'), chipOf('total:>1'), chipOf('status:shipped')])).toEqual({
      filter: { status: 'shipped', field_total_cents: { min: '100' } },
    });
  });
});

describe('review cases', () => {
  // A filterable belongsTo under the remote threshold sends its ids as numbers.
  const author = { ...base, key: 'field_author', name: 'Author', token: 'author', kind: 'select', component: 'select-filter', options: [{ label: 'Ann', value: 3 }] } as unknown as IndexFilter;
  const rating: IndexFilter = { ...base, key: 'rating', name: 'Rating', token: 'rating', kind: 'range', component: 'range-filter' };

  it('takes an option whose value the server sent as a number', () => {
    const parsed = parseToken('author:ann', [author], { softDeletes: false }, __);

    expect('chip' in parsed && parsed.chip.value).toBe('3');
    expect(() => suggest('author:', [author], { softDeletes: false }, __)).not.toThrow();
  });

  it('keeps the decimals of a range side at scale 1', () => {
    const parsed = parseToken('rating:>4.5', [rating], { softDeletes: false }, __);

    expect('chip' in parsed && parsed.chip.value).toEqual({ min: '4.5' });
  });

  it('reads a key with no value as a red chip, never as a search', () => {
    expect(splitInput('status:')).toEqual({ tokens: ['status:'], words: '' });

    const parsed = parseToken('status:', [status], { softDeletes: false }, __);

    expect('chip' in parsed && parsed.chip.error).toBe('Status needs a value');
  });

  it('joins a key to the value after a space', () => {
    expect(splitInput('status: pending ann')).toEqual({ tokens: ['status:pending'], words: 'ann' });
  });
});
