// @vitest-environment jsdom
import { cleanup, render } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { ScrollArea } from '../scroll-area';

// The panel's CSP lets a style tag through only with the server's nonce, which the panel's entry
// script carries. Base UI injects a style tag to hide a scroll area's scrollbar; React hoists one per document, so
// the select's case has a file of its own.
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
  it('carries the page nonce in a scroll area', () => {
    render(
      <ScrollArea className="h-20">
        <p>Long text</p>
      </ScrollArea>,
    );

    expect(scrollbarStyles().length).toBeGreaterThan(0);
    expect(scrollbarStyles().every((style) => style.nonce === 'n0nce' || style.getAttribute('nonce') === 'n0nce')).toBe(true);
  });
});
