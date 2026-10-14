import { lazyNamed } from '../../lib/lazy';

// The select inputs load with a form, not with the index; their cells and details stay eager.
export const SelectForm = lazyNamed(() => import('./SelectForms').then((module) => module.SelectForm));
export const MultiSelectForm = lazyNamed(() => import('./SelectForms').then((module) => module.MultiSelectForm));

/** Loads the select inputs now, so the next first render does not suspend. */
export async function preloadChoiceForms(): Promise<void> {
  await Promise.all([SelectForm.preload(), MultiSelectForm.preload()]);
}
