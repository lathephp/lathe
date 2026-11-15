import type { ReactNode } from 'react';

/** A field's validation message, which the field names in its aria-describedby. The look is the style's cn-field-error. */
export function FieldError({ id, message, icon }: { id: string; message: string | undefined; icon?: ReactNode }) {
  if (message === undefined) {
    return null;
  }

  return (
    <p id={id} role="alert" data-slot="field-error" className="cn-field-error flex gap-1">
      {icon}
      {message}
    </p>
  );
}
