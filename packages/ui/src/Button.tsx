import type { ComponentProps } from 'react';

export interface ButtonProps extends ComponentProps<'button'> {
  variant?: 'default' | 'outline' | 'ghost';
  size?: 'default' | 'sm' | 'icon';
}

const variants = {
  default: 'bg-primary text-primary-foreground hover:bg-primary/90',
  outline: 'border border-border bg-background hover:bg-muted',
  ghost: 'hover:bg-muted',
};

const sizes = {
  default: 'h-9 px-4',
  sm: 'h-8 px-3',
  icon: 'size-9',
};

export function Button({ variant = 'default', size = 'default', type = 'button', className, ...props }: ButtonProps) {
  return (
    <button
      type={type}
      className={[
        'inline-flex shrink-0 items-center justify-center gap-2 rounded-md text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50',
        variants[variant],
        sizes[size],
        className,
      ].join(' ')}
      {...props}
    />
  );
}
