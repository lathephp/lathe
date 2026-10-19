import type { ComponentProps } from 'react';

// The panel's own copy for first paint. The @lathephp/ui entry carries Base UI and
// every kit component, so the first load of a page leaves it out.
export interface ButtonProps extends ComponentProps<'button'> {
  variant?: 'default' | 'outline' | 'ghost' | 'destructive' | 'destructive-solid';
  size?: 'default' | 'sm' | 'icon';
}

export function Button({ variant = 'default', size = 'default', type = 'button', className, ...props }: ButtonProps) {
  return (
    <button
      type={type}
      // The looks live in app.css, below the utilities, so a caller's className still wins.
      className={`lathe-button lathe-button-${variant} lathe-button-size-${size} ${className ?? ''}`}
      {...props}
    />
  );
}
