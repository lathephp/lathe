import { lazyNamed } from '../lib/lazy';

// The saved-view dialogs load when a person opens one, not with the index.
export const SaveTabDialog = lazyNamed(() => import('./SaveTabDialog').then((module) => module.SaveTabDialog));
export const RenameTabDialog = lazyNamed(() => import('./RenameTabDialog').then((module) => module.RenameTabDialog));

/** Loads both dialogs now, so the next first render does not suspend. */
export async function preloadTabDialogs(): Promise<void> {
  await Promise.all([SaveTabDialog.preload(), RenameTabDialog.preload()]);
}
