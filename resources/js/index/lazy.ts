import { lazyNamed } from '../lib/lazy';

// The saved-view dialogs load when a person opens one, not with the index.
export const SaveTabDialog = lazyNamed(() => import('./SaveTabDialog').then((module) => module.SaveTabDialog));
export const RenameTabDialog = lazyNamed(() => import('./RenameTabDialog').then((module) => module.RenameTabDialog));
// The filter panel and its controls load on the first press of Filters.
export const FilterPanel = lazyNamed(() => import('./FilterPanel').then((module) => module.FilterPanel));
// The row menu's list and its delete confirmation load on the menu's first open.
export const RowMenuPanel = lazyNamed(() => import('./RowMenuPanel').then((module) => module.RowMenuPanel));
// The edit-conflict dialog loads when an undo finds the record changed.
export const ConflictDialog = lazyNamed(() => import('../records/ConflictDialog').then((module) => module.ConflictDialog));
// The column picker loads on the first press of Columns.
export const ColumnPicker = lazyNamed(() => import('./ColumnPicker').then((module) => module.ColumnPicker));

/** Loads both dialogs now, so the next first render does not suspend. */
export async function preloadTabDialogs(): Promise<void> {
  await Promise.all([SaveTabDialog.preload(), RenameTabDialog.preload()]);
}
