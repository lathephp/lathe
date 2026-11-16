import { config, renderHook, type RenderHookName } from '@lathephp/core';
import { Badge } from '@lathephp/ui';
import type { ComponentProps } from 'react';

type BadgeVariant = NonNullable<ComponentProps<typeof Badge>['variant']>;

const positions: [RenderHookName, BadgeVariant][] = [
  ['head', 'info'],
  ['header:end', 'success'],
  ['sidebar:before', 'warning'],
  ['sidebar:after', 'secondary'],
  ['page:before', 'outline'],
  ['page:after', 'destructive'],
  ['footer:before', 'default'],
  ['index:header-actions', 'info'],
  ['detail:header-actions', 'success'],
  ['auth:after-form', 'warning'],
];

// The two positions that know their Resource show only on Users.
const usersOnly = new Set<RenderHookName>(['index:header-actions', 'detail:header-actions']);

for (const [position, variant] of positions) {
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
        <Badge data-demo-hook={position} variant={variant}>
          {position}
        </Badge>
      );
    },
    usersOnly.has(position) ? { resources: ['users'] } : {},
  );
}
