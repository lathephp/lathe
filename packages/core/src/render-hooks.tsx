import { lazy, Suspense } from 'react';
import { renderHooks, type RenderHookName, type RenderHookProps } from './registry';

// The error boundaries load only on a page that has a hook, so an empty position costs one lookup.
const HookList = lazy(() => import('./hook-list'));

interface RenderHookPointProps extends RenderHookProps {
  position: RenderHookName;
  className?: string;
}

/** The hooks added at a position, in a wrapper that tests and the demo find by data-lathe-hook. */
export function RenderHook({ position, className = 'contents', ...context }: RenderHookPointProps) {
  const hooks = renderHooks(position).filter(({ resources }) => resources === undefined || (context.resource !== undefined && resources.includes(context.resource)));

  if (hooks.length === 0) {
    return null;
  }

  return (
    <div data-lathe-hook={position} className={className}>
      <Suspense fallback={null}>
        <HookList position={position} hooks={hooks} context={context} />
      </Suspense>
    </div>
  );
}
