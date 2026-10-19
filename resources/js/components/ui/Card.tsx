import type { ComponentProps } from 'react';

// The panel's own copy for first paint. The @lathephp/ui entry carries Base UI and
// every kit component, so the first load of a page leaves it out.
export type CardProps = ComponentProps<'div'>;

export function Card({ className, ...props }: CardProps) {
  return <div className={['rounded-lg border border-border bg-card text-card-foreground shadow-sm', className].join(' ')} {...props} />;
}
