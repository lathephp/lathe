import { describe, expect, it } from 'vitest';
import { pageTitle } from '../lib/title';

describe('pageTitle', () => {
  it('puts the page before the app name', () => {
    expect(pageTitle('Orders', 'Acme Admin')).toBe('Orders · Acme Admin');
  });

  it('shows the app name alone when a page has no title', () => {
    expect(pageTitle('', 'Acme Admin')).toBe('Acme Admin');
  });
});
