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
  const email = userKey((props.auth as SharedProps['auth'] | undefined)?.user);

  claimDrafts(email);
  claimRecentSearches(email);

  if (owner !== undefined && owner !== null && email !== owner) {
    queryClient.clear();
  }

  owner = email;
}

/**
 * Who the browser storage belongs to: the user's email, or their name when the app signs in by
 * username and sends no email; null only when nobody is signed in.
 */
export function userKey(user: SharedProps['auth']['user'] | undefined): string | null {
  if (user === null || user === undefined) {
    return null;
  }

  // The server sends whatever the user model holds, so either may be missing.
  const { email, name } = user as { email?: string | null; name?: string | null };

  return email ?? name ?? '';
}

/**
 * Clears what the browser keeps for the user who is leaving, when an impersonation starts or stops.
 * A visit back to the same URL replaces the page and fires no navigate, so this cannot wait for one.
 */
export function forgetUser(): void {
  claimDrafts(null);
  claimRecentSearches(null);
  queryClient.clear();
  owner = null;
}
