import type { ComponentProps } from 'react';
import { cn } from './lib/utils';

/** A thin bar pinned to the top of the page while something loads. */
function ShimmerBar({ className, ...props }: ComponentProps<'div'>) {
  return (
    <div data-slot="shimmer-bar" className={cn('fixed start-0 end-0 top-0 z-50', className)} {...props}>
      <div className="h-[3px] w-full overflow-hidden">
        <div className="h-full w-2/5 animate-lathe-indeterminate bg-brand motion-reduce:animate-none" />
      </div>
    </div>
  );
}

export { ShimmerBar };
