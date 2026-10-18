import { lazyNamed } from '../../lib/lazy';

// Customize mode loads on the first press of Customize, all three parts from one chunk.
export const CustomizeHeader = lazyNamed(() => import('./CustomizeMode').then((module) => module.CustomizeHeader));
export const EditCardHeader = lazyNamed(() => import('./CustomizeMode').then((module) => module.EditCardHeader));
export const AddPanel = lazyNamed(() => import('./CustomizeMode').then((module) => module.AddPanel));

/** Loads Customize mode now, so entering it does not suspend. */
export async function preloadCustomize(): Promise<void> {
  await Promise.all([CustomizeHeader.preload(), EditCardHeader.preload(), AddPanel.preload()]);
}
