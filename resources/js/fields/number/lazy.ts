import { lazyNamed } from '../../lib/lazy';

// The token input loads with a form, not with the index; its cell and detail stay eager.
export const TokenInput = lazyNamed(() => import('./TokenInput').then((module) => module.TokenInput));
