// @vitest-environment jsdom
import { cleanup, render } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../select';

// The panel's CSP lets a style tag through only with the server's nonce, which the panel's entry
// script carries. Base UI injects a style tag to hide a select list's scrollbar, which a select list shares.
beforeAll(() => {
  const entry = document.createElement('script');
  entry.type = 'module';
  entry.nonce = 'n0nce';
  document.head.append(entry);
});

afterEach(cleanup);

function scrollbarStyles(): HTMLStyleElement[] {
  return Array.from(document.querySelectorAll('style')).filter((style) => style.textContent.includes('base-ui-disable-scrollbar'));
}

describe("the kit's injected scrollbar style", () => {
  it('carries the page nonce in an open select', () => {
    render(
      <Select defaultOpen items={{ a: 'Apple' }}>
        <SelectTrigger aria-label="Fruit">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="a">Apple</SelectItem>
        </SelectContent>
      </Select>,
    );

    expect(scrollbarStyles().length).toBeGreaterThan(0);
    expect(scrollbarStyles().every((style) => style.nonce === 'n0nce' || style.getAttribute('nonce') === 'n0nce')).toBe(true);
  });
});
