import type { SelectionDescriptor } from '../index/useRowSelection';

/** The selection of one record, trashed or not. */
export function recordSelection(id: string | number): SelectionDescriptor {
  return { mode: 'ids', ids: [id], exclusions: [], q: '', filters: {}, tab: null, saved_tab: null, trashed: 'with', lens: null, via: null };
}
