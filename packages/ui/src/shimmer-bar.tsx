import type { ComponentProps } from 'react';
import { cn } from './lib/utils';

/** A thin bar pinned to the top of the page while something loads. */
function ShimmerBar({ className, ...props }: ComponentProps<'div'>) {
  return (
    <div data-slot="shimmer-bar" className={cn('fixed start-0 end-0 top-0 z-50', className)} {...props}>
      <div className="h-0.5 w-full overflow-hidden bg-primary/15">
        <div className="h-full w-1/3 animate-pulse rounded-full bg-primary/70" />
      </div>
    </div>
  );
}

export { ShimmerBar };
