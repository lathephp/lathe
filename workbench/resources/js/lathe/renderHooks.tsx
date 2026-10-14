import { config, renderHook, type RenderHookName } from '@lathephp/core';
import { Badge } from '@lathephp/ui';

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

// The two positions that know their Resource show only on Users.
const usersOnly = new Set<RenderHookName>(['index:header-actions', 'detail:header-actions']);

for (const [position, colour] of positions) {
  renderHook(
    position,
    function DemoHookBadge() {
      // The live demo's server sets this flag, and visitors see no developer badges.
      if (config('demo') === true) {
        return null;
      }

      // The head position is for <Head> content, so its marker is hidden rather than drawn in the shell.
      if (position === 'head') {
        return <span hidden data-demo-hook="head" />;
      }

      return (
        <Badge data-demo-hook={position} className={`${colour} text-white`}>
          {position}
        </Badge>
      );
    },
    usersOnly.has(position) ? { resources: ['users'] } : {},
  );
}
