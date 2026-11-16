'use no memo';

import type { ComponentProps } from 'react';

// The panel's own copy for first paint. The @lathephp/ui entry carries Base UI and
// every kit component, so the first load of a page leaves it out.
export type ButtonVariant = 'default' | 'outline' | 'secondary' | 'ghost' | 'destructive' | 'destructive-solid' | 'link';
export type ButtonSize = 'default' | 'xs' | 'sm' | 'lg' | 'icon' | 'icon-xs' | 'icon-sm' | 'icon-lg';

export interface ButtonProps extends ComponentProps<'button'> {
  variant?: ButtonVariant;
  size?: ButtonSize;
}

/**
 * The kit Button's classes, for a Link drawn as a button. The look is the style stylesheet's
 * cn-button rules; lathe-button carries the structure and the panel's layout hooks.
 */
export function buttonClass(variant: ButtonVariant = 'default', size: ButtonSize = 'default'): string {
  return `lathe-button cn-button cn-button-variant-${variant} cn-button-size-${size}`;
}

export function Button({ variant, size, type = 'button', className, ...props }: ButtonProps) {
  // A caller's className comes last, so its utilities beat the style's base-layer rules.
  return <button type={type} className={`${buttonClass(variant, size)} ${className ?? ''}`} {...props} />;
}

/** The kit CollapsibleTrigger's copy: a plain button that shows or hides a panel, with the kit's slot and open state. */
export function CollapsibleTrigger({ open, ...props }: Omit<ComponentProps<'button'>, 'type'> & { open: boolean }) {
  return <button type="button" aria-expanded={open} data-slot="collapsible-trigger" data-panel-open={open ? '' : undefined} {...props} />;
}
