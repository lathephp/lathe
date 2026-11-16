'use no memo';

import { Check, Minus } from 'lucide-react';
import type { ComponentProps } from 'react';

export interface CheckboxProps extends Omit<ComponentProps<'button'>, 'onChange'> {
  checked: boolean;
  /** Neither on nor off, such as a nullable boolean with no value. */
  indeterminate?: boolean;
  onCheckedChange: (checked: boolean) => void;
}

/**
 * The panel's first-paint copy of the kit Checkbox: a button with the kit's role, slots and state
 * attributes, so the style's cn-checkbox rules draw it without loading Base UI.
 */
export function Checkbox({ checked, indeterminate = false, onCheckedChange, className, disabled, ...props }: CheckboxProps) {
  const state = indeterminate ? { 'data-indeterminate': '' } : checked ? { 'data-checked': '' } : { 'data-unchecked': '' };

  return (
    <button
      type="button"
      role="checkbox"
      aria-checked={indeterminate ? 'mixed' : checked}
      data-slot="checkbox"
      data-disabled={disabled === true ? '' : undefined}
      disabled={disabled}
      {...state}
      onClick={() => {
        onCheckedChange(!checked);
      }}
      className={`cn-checkbox group/checkbox peer relative shrink-0 outline-none after:absolute after:-inset-x-3 after:-inset-y-2 data-disabled:cursor-not-allowed data-disabled:opacity-50 ${className ?? ''}`}
      {...props}
    >
      {(checked || indeterminate) && (
        <span data-slot="checkbox-indicator" {...state} className="cn-checkbox-indicator grid place-content-center text-current transition-none">
          {indeterminate ? <Minus /> : <Check />}
        </span>
      )}
    </button>
  );
}
