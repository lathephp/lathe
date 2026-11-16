'use no memo';

import type { ComponentProps } from 'react';

// The panel's first-paint Input and Textarea, copies of the kit's: the look is the style
// stylesheet's cn-input and cn-textarea rules; lathe-input carries the structure. The class
// names stay exported for a part that draws an input of its own, such as a date text field.
export const inputClass = 'lathe-input cn-input';
export const textareaClass = 'lathe-input cn-textarea';

export function Input({ className, ...props }: ComponentProps<'input'>) {
  return <input data-slot="input" className={`${inputClass} ${className ?? ''}`} {...props} />;
}

export function Textarea({ className, ...props }: ComponentProps<'textarea'>) {
  return <textarea data-slot="textarea" className={`${textareaClass} ${className ?? ''}`} {...props} />;
}
