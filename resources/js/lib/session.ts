import { queryClient } from '@lathephp/core';
import type { SharedProps } from '../types';

// The email of the user the cached queries belong to; undefined until the first page.
let owner: string | null | undefined;

/**
 * Drops every cached query when a signed-in user leaves, at sign-out or impersonation, so a card or
 * a search never shows one user's values to another. A sign-in follows a sign-out that already
 * emptied the cache, and its page's cards have asked for their values by now, so it keeps them.
 */
export function followUser(props: Record<string, unknown>): void {
  const email = (props.auth as SharedProps['auth'] | undefined)?.user?.email ?? null;

  if (owner !== undefined && owner !== null && email !== owner) {
    queryClient.clear();
  }

  owner = email;
}
