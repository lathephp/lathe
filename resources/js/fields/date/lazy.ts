import { lazyNamed } from '../../lib/lazy';

// The date inputs load on a form or an open filter panel, not with the index; the calendar and
// its popover load later still, on the first open.
export const DateInput = lazyNamed(() => import('./DateInput').then((module) => module.DateInput));
export const DateTimeInput = lazyNamed(() => import('./DateInput').then((module) => module.DateTimeInput));
export const DateText = lazyNamed(() => import('./DateText').then((module) => module.DateText));
export const DatePicker = lazyNamed(() => import('./DatePicker').then((module) => module.DatePicker));

/** Loads every date chunk now, so the next first render of each does not suspend. */
export async function preloadDateFields(): Promise<void> {
  await Promise.all([DateInput, DateTimeInput, DateText, DatePicker].map((component) => component.preload()));
}
