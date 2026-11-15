import type { ComponentProps } from 'react';

// The panel's own copy for first paint. The @lathephp/ui entry carries Base UI and
// every kit component, so the first load of a page leaves it out. The look is the
// active style's cn-card rules, and .lathe-card in app.css holds the flex column.
export type CardProps = ComponentProps<'div'>;

export function Card({ className, ...props }: CardProps) {
  return <div data-slot="card" className={['lathe-card cn-card', className].join(' ')} {...props} />;
}

export function CardHeader({ className, ...props }: CardProps) {
  return (
    <div
      data-slot="card-header"
      className={[
        'cn-card-header group/card-header @container/card-header grid auto-rows-min items-start has-data-[slot=card-action]:grid-cols-[1fr_auto] has-data-[slot=card-description]:grid-rows-[auto_auto]',
        className,
      ].join(' ')}
      {...props}
    />
  );
}

export function CardTitle({ className, ...props }: CardProps) {
  return <div data-slot="card-title" className={['cn-card-title cn-font-heading', className].join(' ')} {...props} />;
}

export function CardDescription({ className, ...props }: CardProps) {
  return <div data-slot="card-description" className={['cn-card-description', className].join(' ')} {...props} />;
}

export function CardAction({ className, ...props }: CardProps) {
  return <div data-slot="card-action" className={['cn-card-action col-start-2 row-span-2 row-start-1 self-start justify-self-end', className].join(' ')} {...props} />;
}

export function CardContent({ className, ...props }: CardProps) {
  return <div data-slot="card-content" className={['cn-card-content', className].join(' ')} {...props} />;
}

export function CardFooter({ className, ...props }: CardProps) {
  return <div data-slot="card-footer" className={['cn-card-footer flex items-center', className].join(' ')} {...props} />;
}
