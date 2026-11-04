/// <reference types="node" />
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

// Tailwind's utilities layer sits above the components layer, so a component rule that sets display
// on a class does nothing when the element also carries a display utility. jsdom cannot see it.

interface Source {
  path: string;
  text: string;
}

interface Offender {
  className: string;
  cssLine: number;
  path: string;
  line: number;
}

interface DisplayRule {
  classes: string[];
  line: number;
}

const DISPLAY_UTILITIES = new Set(['flex', 'inline-flex', 'grid', 'inline-grid', 'block', 'inline-block', 'inline', 'contents', 'hidden', 'table', 'flow-root', 'list-item']);

const lineAt = (text: string, index: number) => text.slice(0, index).split('\n').length;

// Split at top-level commas only, not those inside :where() or :not().
const splitSelectors = (list: string) => list.split(/,(?![^(]*\))/).map((selector) => selector.trim());

// The classes of a selector's last compound: `.a .b > .c.d:hover` gives c and d.
function targetClasses(selector: string): string[] {
  const bare = selector.replace(/\([^)]*\)/g, '').replace(/\[[^\]]*\]/g, '');
  const last = bare.split(/[\s>+~]+/).at(-1) ?? '';

  return [...last.matchAll(/\.([\w-]+)/g)].map((match) => match[1] ?? '');
}

// `@apply contents!` and `display: none !important` beat a utility, so they are not refused.
function setsDisplay(declaration: string): boolean {
  const text = declaration.trim();
  if (text.startsWith('@apply')) {
    return text
      .slice('@apply'.length)
      .split(/\s+/)
      .some((token) => !token.endsWith('!') && DISPLAY_UTILITIES.has(token.split(':').at(-1) ?? ''));
  }

  return /^display\s*:/.test(text) && !/!\s*important/.test(text);
}

function displayRules(css: string): DisplayRule[] {
  const text = css.replace(/\/\*[\s\S]*?\*\//g, (comment) => comment.replace(/[^\n]/g, ' '));
  const rules: DisplayRule[] = [];
  const stack: string[] = [];
  let start = 0;

  const declare = (end: number) => {
    const prelude = stack.at(-1) ?? '';
    const inComponents = stack.some((block) => /^@layer\s+components$/.test(block));
    const declaration = text.slice(start, end);
    if (inComponents && !prelude.startsWith('@') && setsDisplay(declaration)) {
      const offset = declaration.length - declaration.trimStart().length;
      for (const selector of splitSelectors(prelude)) rules.push({ classes: targetClasses(selector), line: lineAt(text, start + offset) });
    }
  };

  for (let index = 0; index < text.length; index++) {
    const char = text[index];
    if (char === '{') {
      stack.push(text.slice(start, index).trim());
      start = index + 1;
    } else if (char === ';') {
      declare(index);
      start = index + 1;
    } else if (char === '}') {
      declare(index);
      stack.pop();
      start = index + 1;
    }
  }

  return rules;
}

// The string literals of a `className="..."` or `className={...}` expression, split into tokens.
function classNameExpressions(source: string): { tokens: string[]; line: number }[] {
  const found: { tokens: string[]; line: number }[] = [];
  // A comment's apostrophe would pair with a real quote, so comments are blanked first, keeping line numbers.
  const text = source.replace(/(^|\s)\/\/.*$|\/\*[\s\S]*?\*\//gm, (comment) => comment.replace(/[^\s]/g, ' '));

  for (const match of text.matchAll(/\bclassName=/g)) {
    const from = match.index + match[0].length;
    let end = from;
    if (text[from] === '"' || text[from] === "'") {
      end = text.indexOf(text[from] ?? '"', from + 1) + 1;
    } else if (text[from] === '{') {
      let depth = 0;
      let quote = '';
      for (end = from; end < text.length; end++) {
        const char = text[end];
        if (quote) {
          if (char === '\\') end++;
          else if (char === quote) quote = '';
        } else if (char === '"' || char === "'" || char === '`') quote = char;
        else if (char === '{') depth++;
        else if (char === '}' && --depth === 0) break;
      }
    }

    const tokens = [...text.slice(from, end).matchAll(/(["'`])((?:\\.|(?!\1)[^\\])*)\1/g)].flatMap((literal) => (literal[2] ?? '').split(/\s+/)).filter(Boolean);
    found.push({ tokens, line: lineAt(text, from) });
  }

  return found;
}

// A variant aimed at a descendant (`[&>div]:flex`, `*:flex`) leaves the element's own display alone.
// `hidden` is skipped: `md:hidden` or `max-md:hidden` on a component class is a deliberate responsive
// switch that is meant to beat the component's display, not a rule that silently does nothing.
const hasDisplayUtility = (tokens: string[]) =>
  tokens.some((token) => {
    const parts = token.split(':');
    const utility = parts.at(-1) ?? '';

    return utility !== 'hidden' && DISPLAY_UTILITIES.has(utility) && !parts.slice(0, -1).some((variant) => variant.startsWith('[&') || variant.startsWith('*'));
  });

function scan(css: string, sources: Source[]): Offender[] {
  const rules = displayRules(css);
  const offenders: Offender[] = [];

  for (const { path, text } of sources) {
    for (const { tokens, line } of classNameExpressions(text)) {
      if (!hasDisplayUtility(tokens)) continue;
      for (const rule of rules) {
        if (rule.classes.length > 0 && rule.classes.every((name) => tokens.includes(name))) {
          offenders.push({ className: rule.classes.join('.'), cssLine: rule.line, path, line });
        }
      }
    }
  }

  return offenders;
}

function tsxFiles(directory: string): string[] {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) return ['node_modules', 'dist', '__tests__'].includes(entry.name) ? [] : tsxFiles(path);

    return entry.name.endsWith('.tsx') ? [path] : [];
  });
}

const fixtureCss = (apply: string) => `@layer components { .x .y { ${apply} } }`;
const fixtureTsx = (className: string): Source[] => [{ path: 'Fixture.tsx', text: `export const A = () => <div className="${className}" />;` }];

describe('scan', () => {
  it('reports a class whose components rule sets display while the element has a display utility', () => {
    expect(scan(fixtureCss('@apply contents;'), fixtureTsx('y flex gap-2')).map((o) => o.className)).toEqual(['y']);
  });

  it('lets an important display rule through', () => {
    expect(scan(fixtureCss('@apply contents!;'), fixtureTsx('y flex gap-2'))).toEqual([]);
  });

  it('reports nothing when the element has no display utility', () => {
    expect(scan(fixtureCss('@apply contents;'), fixtureTsx('y flex-wrap grid-cols-2 gap-2'))).toEqual([]);
  });
});

describe('the components layer', () => {
  it('sets display on no class that an element also gives a display utility', () => {
    // Read from disk: Vitest turns a stylesheet import into an empty string.
    const css = readFileSync('resources/css/app.css', 'utf8');
    const roots = ['resources/js', ...readdirSync('packages').map((name) => join('packages', name, 'src'))];
    const sources = roots.flatMap((root) => {
      try {
        return tsxFiles(root).map((path) => ({ path, text: readFileSync(path, 'utf8') }));
      } catch {
        return [];
      }
    });

    expect(displayRules(css).length).toBeGreaterThan(3);
    expect(sources.length).toBeGreaterThan(10);
    expect(scan(css, sources).map((o) => `.${o.className} (app.css:${String(o.cssLine)}) on ${o.path}:${String(o.line)}`)).toEqual([]);
  });
});
