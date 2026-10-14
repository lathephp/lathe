import { cn } from './lib/utils';

import { Loader2Icon } from './lib/icons';

function Spinner({ className, 'aria-label': label = 'Loading', ...props }: React.ComponentProps<'svg'>) {
  return (
    <Loader2Icon
      data-slot="spinner"
      role="status"
      aria-label={label}
      className={cn('size-4 animate-spin', className)}
      {...props}
    />
  );
}

export { Spinner };
