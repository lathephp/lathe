'use no memo';

import type { ComponentProps } from 'react';

export interface SwitchProps extends Omit<ComponentProps<'button'>, 'onChange'> {
  checked: boolean;
  onCheckedChange: (checked: boolean) => void;
}

/**
 * The panel's first-paint copy of the kit Switch: a button with the kit's role, slots and state
 * attributes, so the style's cn-switch rules draw it without loading Base UI.
 */
export function Switch({ checked, onCheckedChange, className, disabled, ...props }: SwitchProps) {
  const state = checked ? { 'data-checked': '' } : { 'data-unchecked': '' };

  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      data-slot="switch"
      data-size="default"
      data-disabled={disabled === true ? '' : undefined}
      disabled={disabled}
      {...state}
      onClick={() => {
        onCheckedChange(!checked);
      }}
      className={`cn-switch peer group/switch relative inline-flex items-center transition-all outline-none after:absolute after:-inset-x-3 after:-inset-y-2 data-disabled:cursor-not-allowed data-disabled:opacity-50 ${className ?? ''}`}
      {...props}
    >
      <span data-slot="switch-thumb" {...state} className="cn-switch-thumb pointer-events-none block ring-0 transition-transform" />
    </button>
  );
}
