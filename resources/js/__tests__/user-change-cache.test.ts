import { queryClient } from '@lathephp/core';
import { describe, expect, it } from 'vitest';
import { followUser } from '../lib/session';

const as = (email: string | null) => ({ auth: { user: email === null ? null : { name: email, email } } });

describe('the query cache across users', () => {
  it('keeps cached values while the same user browses and drops them when the user changes', () => {
    followUser(as('admin@lathephp.com'));
    queryClient.setQueryData(['widgets', 'sales'], 42);

    followUser(as('admin@lathephp.com'));
    expect(queryClient.getQueryData(['widgets', 'sales'])).toBe(42);

    followUser(as(null));
    expect(queryClient.getQueryData(['widgets', 'sales'])).toBeUndefined();

    queryClient.setQueryData(['widgets', 'sales'], 7);
    followUser(as('editor@lathephp.com'));
    expect(queryClient.getQueryData(['widgets', 'sales'])).toBeUndefined();
  });
});
