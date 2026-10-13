import type { ComponentProps } from 'react';

export type CardProps = ComponentProps<'div'>;

export function Card({ className, ...props }: CardProps) {
  return <div className={['rounded-lg border border-border bg-background', className].join(' ')} {...props} />;
}
