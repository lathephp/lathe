import type { Row } from '../index/types';
import type { RowSelection } from '../index/useRowSelection';

export type BatchOperation = 'delete' | 'forceDelete' | 'restore';

/** What the Delete menu offers for the ticked rows. */
export function batchOperations(softDeletes: boolean, selection: RowSelection, rows: Row[]): BatchOperation[] {
  const ticked = selection.all ? rows : rows.filter((row) => selection.isSelected(row.id));

  if (!selection.all && selection.selected.size === 0) {
    return [];
  }

  const operations: BatchOperation[] = [];

  if (ticked.some((row) => row.trashed !== true && row.abilities.delete === true)) {
    operations.push('delete');
  }

  if (softDeletes && ticked.some((row) => row.abilities.forceDelete === true)) {
    operations.push('forceDelete');
  }

  if (softDeletes && ticked.some((row) => row.trashed === true && row.abilities.restore === true)) {
    operations.push('restore');
  }

  return operations;
}
