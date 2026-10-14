import * as React from 'react';
import { cn } from './lib/utils';

function Label({ className, ...props }: React.ComponentProps<'label'>) {
  return (
    // eslint-disable-next-line jsx-a11y/label-has-associated-control -- the caller pairs it with a control by htmlFor or nesting.
    <label
      data-slot="label"
      className={cn(
        'cn-label flex items-center select-none group-data-[disabled=true]:pointer-events-none peer-disabled:cursor-not-allowed',
        className,
      )}
      {...props}
    />
  );
}

export { Label };
