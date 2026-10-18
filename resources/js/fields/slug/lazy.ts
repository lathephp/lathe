import { lazyNamed } from '../../lib/lazy';

// The slug input loads with a form, not with the index.
export const SlugInput = lazyNamed(() => import('./SlugInput').then((module) => module.default));
