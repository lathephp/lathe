import { queryClient } from '@lathephp/core';
import { describe, expect, it } from 'vitest';
import { followUser } from '../lib/session';

const as = (email: string | null) => ({ auth: { user: email === null ? null : { name: email, email } } });

describe('the query cache across users', () => {
  it('keeps cached values while the same user browses and drops them when that user leaves', () => {
    followUser(as('admin@lathephp.com'));
    queryClient.setQueryData(['widgets', 'sales'], 42);

    followUser(as('admin@lathephp.com'));
    expect(queryClient.getQueryData(['widgets', 'sales'])).toBe(42);

    followUser(as(null));
    expect(queryClient.getQueryData(['widgets', 'sales'])).toBeUndefined();
  });

  // Sign-out emptied the cache, and the signed-in page's cards have already asked for their values by the time it lands.
  it("keeps what a page fetched for the user who just signed in, and drops it at an impersonation", () => {
    followUser(as(null));
    queryClient.setQueryData(['widgets', 'sales'], 7);

    followUser(as('editor@lathephp.com'));
    expect(queryClient.getQueryData(['widgets', 'sales'])).toBe(7);

    followUser(as('admin@lathephp.com'));
    expect(queryClient.getQueryData(['widgets', 'sales'])).toBeUndefined();
  });
});
