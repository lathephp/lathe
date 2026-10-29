import { lazyNamed } from '../../lib/lazy';

// The relation pickers and the record preview load on a form or on demand, not with the index.
export const BelongsToForm = lazyNamed(() => import('./BelongsToForm').then((module) => module.BelongsToForm));
export const MorphToForm = lazyNamed(() => import('./MorphToForm').then((module) => module.MorphToForm));
export const TagForm = lazyNamed(() => import('./TagForm').then((module) => module.TagForm));
export const HasOneForm = lazyNamed(() => import('./HasOneForm').then((module) => module.HasOneForm));
export const PreviewPanel = lazyNamed(() => import('../../index/PreviewPanel').then((module) => module.PreviewPanel));

/** Loads every relation chunk now, so the next first render of each does not suspend. */
export async function preloadRelationForms(): Promise<void> {
  await Promise.all([BelongsToForm, MorphToForm, TagForm, HasOneForm, PreviewPanel].map((component) => component.preload()));
}
