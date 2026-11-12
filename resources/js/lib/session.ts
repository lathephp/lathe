import { queryClient } from '@lathephp/core';
import { claimRecentSearches } from '../palette/useRecentSearches';
import { claimDrafts } from '../records/useFormDraft';
import type { SharedProps } from '../types';

// The email of the user the cached queries belong to; undefined until the first page.
let owner: string | null | undefined;

/**
 * Drops every cached query when a signed-in user leaves, at sign-out or impersonation, so a card or
 * a search never shows one user's values to another. A sign-in follows a sign-out that already
 * emptied the cache, and its page's cards have asked for their values by now, so it keeps them.
 * The form drafts and recent searches kept in the browser go the same way, across reloads too.
 */
export function followUser(props: Record<string, unknown>): void {
  const email = (props.auth as SharedProps['auth'] | undefined)?.user?.email ?? null;

  claimDrafts(email);
  claimRecentSearches(email);

  if (owner !== undefined && owner !== null && email !== owner) {
    queryClient.clear();
  }

  owner = email;
}
