import { lazyNamed } from '../../lib/lazy';

// The key-value table loads with a form or a detail page that has one, not with the index.
export const KeyValueForm = lazyNamed(() => import('./KeyValueFields').then((module) => module.default));
export const KeyValueDetail = lazyNamed(() => import('./KeyValueFields').then((module) => module.KeyValueDetail));
