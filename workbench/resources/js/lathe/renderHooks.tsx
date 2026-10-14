import { renderHook, type RenderHookName } from '@lathephp/core';
import { Badge } from '@lathephp/ui';
import type { CSSProperties } from 'react';

// White text on a 700 or 800 shade keeps each badge above the 4.5:1 contrast floor.
const positions: [RenderHookName, string][] = [
  ['head', 'bg-sky-700'],
  ['header:end', 'bg-emerald-700'],
  ['sidebar:before', 'bg-violet-700'],
  ['sidebar:after', 'bg-rose-700'],
  ['page:before', 'bg-amber-800'],
  ['page:after', 'bg-teal-700'],
  ['footer:before', 'bg-indigo-700'],
  ['index:header-actions', 'bg-fuchsia-700'],
  ['detail:header-actions', 'bg-orange-800'],
  ['auth:after-form', 'bg-lime-800'],
];

// The head position sits in the shell's flex row, so its badge floats in a corner and lets clicks through.
const floating: CSSProperties = { position: 'fixed', insetBlockEnd: 8, insetInlineEnd: 8, zIndex: 50, pointerEvents: 'none' };

// The two positions that know their Resource show only on Users.
const usersOnly = new Set<RenderHookName>(['index:header-actions', 'detail:header-actions']);

for (const [position, colour] of positions) {
  renderHook(
    position,
    function DemoHookBadge() {
      return (
        <Badge data-demo-hook={position} className={`${colour} text-white`} style={position === 'head' ? floating : undefined}>
          {position}
        </Badge>
      );
    },
    usersOnly.has(position) ? { resources: ['users'] } : {},
  );
}
