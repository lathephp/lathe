import { lazyNamed } from '../../lib/lazy';

// The repeater's rows load on a form or a detail page, not with the index.
export const RepeaterForm = lazyNamed(() => import('./RepeaterFields').then((module) => module.RepeaterForm));
export const RepeaterDetail = lazyNamed(() => import('./RepeaterFields').then((module) => module.RepeaterDetail));

/** Loads the repeater chunk now, so the next first render does not suspend. */
export async function preloadRepeaterFields(): Promise<void> {
  await Promise.all([RepeaterForm.preload(), RepeaterDetail.preload()]);
}
