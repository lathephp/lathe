/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// Each surface draws as the style's part, so the style gives it its corners and shadow, and the
// panel stylesheet gives it its layer. The inner controls keep their own corners.
const surfaces = [
  ['resources/js/notifications/NotificationPanel.tsx', 'cn-popover-content'],
  ['resources/js/fields/relation/PeekCard.tsx', 'cn-popover-content'],
  ['resources/js/palette/SearchPalette.tsx', 'cn-dialog-content'],
  ['resources/js/widgets/HelpTooltip.tsx', 'cn-tooltip-content'],
  ['resources/js/layout/NavSection.tsx', 'cn-tooltip-content'],
] as const;

describe.each(surfaces)('%s', (path, part) => {
  const source = readFileSync(path, 'utf8');

  it('names no layer or shadow class', () => {
    expect(source.match(/(?<![\w-])(z-[\d[]|shadow(?![\w]))/g)).toBeNull();
  });

  it(`draws its surface as ${part}, with no corner class of its own`, () => {
    const surface = [...source.matchAll(/(["'`])([^"'`]*)\1/g)].map((match) => match[2] ?? '').filter((text) => text.split(/\s+/).includes(part));

    expect(surface).toHaveLength(1);
    expect(surface[0]).not.toMatch(/(?<![\w-])rounded(?![\w])/);
  });
});

describe('the search palette on a phone', () => {
  it('fills the screen with no corners', () => {
    const panel = readFileSync('resources/css/app.css', 'utf8');

    expect(panel).toMatch(/@media \(width < 48rem\) \{\s*\[data-lathe-palette\] > \[role="dialog"\] \{\s*@apply rounded-none;/);
  });
});
