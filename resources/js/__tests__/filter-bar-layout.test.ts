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

describe('the filter bar on a phone', () => {
  it('opens its suggestions as a sheet along the bottom edge, as wide as the screen', () => {
    const phone = media('width < 48rem', '.lathe-bar-list');

    // The positioner places the list with inline styles, so the sheet overrides them.
    expect(rule(phone, ':has(> .lathe-bar-list)')).toEqual(expect.arrayContaining(['fixed!', 'inset-x-0!', 'top-auto!', 'bottom-0!', 'transform-none!']));
    expect(rule(phone, '.lathe-bar-list')).toEqual(expect.arrayContaining(['w-full!', 'max-w-none!', 'rounded-b-none', 'rounded-t-2xl', 'max-h-[60svh]!']));
  });
});

describe('the views rail', () => {
  it('shows from 1100 px and folds into the Views menu below it', () => {
    expect(rule(css, '.lathe-views-rail')).toContain('hidden');

    const wide = media('width >= 1100px', '.lathe-views-rail');

    expect(rule(wide, '.lathe-views-rail')).toContain('flex');
    expect(rule(wide, '.lathe-views-menu')).toContain('hidden');
  });

  it('folds into the Views menu beside an open preview panel from 1280 px', () => {
    const wide = media('width >= 80rem', '[data-preview-open] > .lathe-views-rail');

    expect(rule(wide, '[data-preview-open] > .lathe-views-rail')).toContain('hidden');
    expect(rule(wide, '[data-preview-open] .lathe-views-menu')).toContain('block');
  });
});
