import { queryClient } from '@lathephp/core';
import type { SharedProps } from '../types';

// The email of the user the cached queries belong to; undefined until the first page.
let owner: string | null | undefined;

/**
 * Drops every cached query when the signed-in user changes, as at sign-out, sign-in or
 * impersonation, so a card or a search never shows one user's values to another.
 */
export function followUser(props: Record<string, unknown>): void {
  const email = (props.auth as SharedProps['auth'] | undefined)?.user?.email ?? null;

  if (owner !== undefined && email !== owner) {
    queryClient.clear();
  }

  owner = email;
}
