import { describe, expect, it } from 'vitest';
import { english } from '../translation';

describe('english', () => {
  it('fills the longest placeholder first, so :to never eats :total', () => {
    expect(english('Showing :from to :to of :total', { from: 1, to: 25, total: '10,000' })).toBe('Showing 1 to 25 of 10,000');
  });
});
