import { describe, expect, it } from 'vitest';
import { applyChanges, parseQuery, toQuery } from '../index/useIndexState';

const defaults = { perPage: 25 };

describe('index url state', () => {
  it('reads scalar, list and object filters', () => {
    const search = '?filter[status]=pending&filter[tag][]=a&filter[tag][]=b&filter[total][min]=10';

    expect(parseQuery(search)).toEqual({
      filter: { status: 'pending', tag: ['a', 'b'], total: { min: '10' } },
    });
  });

  it('reads a list the router writes with numbered keys as a list', () => {
    expect(parseQuery('?filter[status][0]=pending&filter[status][1]=paid')).toEqual({
      filter: { status: ['pending', 'paid'] },
    });
  });

  it('reads the encoded form of the brackets', () => {
    expect(parseQuery('?filter%5Bstatus%5D=pending&filter%5Btag%5D%5B%5D=a')).toEqual({
      filter: { status: 'pending', tag: ['a'] },
    });
  });

  it('reads the plain params', () => {
    expect(
      parseQuery('?q=ada&sort=-created_at,name&page=3&per_page=50&tab=open&saved_tab=7&group=status:desc&trashed=with'),
    ).toEqual({
      q: 'ada',
      sort: '-created_at,name',
      page: 3,
      per_page: 50,
      tab: 'open',
      saved_tab: '7',
      group: 'status:desc',
      trashed: 'with',
    });
  });

  it('writes the filters back in the same shape', () => {
    const state = { filter: { status: 'pending', tag: ['a', 'b'], total: { min: '10' } } };

    expect(toQuery(state, defaults)).toEqual(state);
  });

  it('leaves out every default value', () => {
    expect(
      toQuery(
        { q: '', sort: undefined, page: 1, per_page: 25, tab: undefined, filter: { status: '', tag: [], total: { min: '' } } },
        defaults,
      ),
    ).toEqual({});
  });

  it('keeps a page past 1 and a page size that is not the first option', () => {
    expect(toQuery({ page: 2, per_page: 50 }, defaults)).toEqual({ page: 2, per_page: 50 });
  });

  it('drops the page when the search changes', () => {
    expect(applyChanges({ page: 3, per_page: 50 }, { q: 'ada' })).toEqual({ q: 'ada', per_page: 50 });
  });

  it.each([
    ['sort', { sort: 'name' }],
    ['filter', { filter: { status: 'paid' } }],
    ['tab', { tab: 'open' }],
    ['saved_tab', { saved_tab: '2' }],
    ['group', { group: 'status' }],
    ['trashed', { trashed: 'only' }],
    ['per_page', { per_page: 100 }],
  ])('drops the page when %s changes', (_name, changes) => {
    expect(applyChanges({ page: 3 }, changes).page).toBeUndefined();
  });

  it('keeps the other params when only the page changes', () => {
    expect(applyChanges({ q: 'ada', sort: 'name', page: 2 }, { page: 3 })).toEqual({ q: 'ada', sort: 'name', page: 3 });
  });
});
