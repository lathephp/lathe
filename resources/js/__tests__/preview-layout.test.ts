/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// Read from disk: Vitest turns a stylesheet import into an empty string.
const css = readFileSync('resources/css/app.css', 'utf8');

/** The body of the first `@media (<query>)` block that holds `selector`, braces balanced. */
function media(query: string, selector: string): string {
  let from = 0;

  for (;;) {
    const start = css.indexOf(`@media (${query}) {`, from);

    if (start === -1) {
      return '';
    }

    let depth = 0;
    let end = start;

    for (let index = css.indexOf('{', start); index < css.length; index++) {
      depth += css[index] === '{' ? 1 : css[index] === '}' ? -1 : 0;

      if (depth === 0) {
        end = index;
        break;
      }
    }

    const body = css.slice(start, end);

    if (body.includes(selector)) {
      return body;
    }

    from = end;
  }
}

/** The @apply list of a rule, read from `source`. */
function rule(source: string, selector: string): string[] {
  const at = source.indexOf(`${selector} {`);
  const match = /@apply ([^;]+);/.exec(source.slice(at));

  return at === -1 || match === null ? [] : (match[1] ?? '').split(/\s+/);
}

describe('the preview panel layout', () => {
  it('is 400 px wide and lies over the table, with a shadow, below 1280 px', () => {
    expect(rule(css, '.lathe-preview')).toEqual(expect.arrayContaining(['fixed', 'end-0', 'w-[400px]', 'shadow-xl']));
  });

  it('sits beside the card from 1280 px, which narrows to make room', () => {
    const wide = media('width >= 80rem', '.lathe-index-split');

    expect(rule(wide, '.lathe-index-split')).toEqual(expect.arrayContaining(['flex']));
    expect(rule(wide, '.lathe-index-split > [data-lathe-card]')).toEqual(expect.arrayContaining(['min-w-0', 'flex-1']));
    // Only the list's own panel joins the row; a tag's panel keeps lying over the page.
    expect(rule(wide, '.lathe-index-split > .lathe-preview')).toEqual(expect.arrayContaining(['sticky', 'shrink-0', 'shadow-none']));
    expect(wide).not.toMatch(/\n\s*\.lathe-preview \{/);
  });

  it('fills the screen below 768 px', () => {
    expect(rule(media('width < 48rem', '.lathe-preview'), '.lathe-preview')).toEqual(expect.arrayContaining(['inset-0', 'w-auto']));
  });
});
