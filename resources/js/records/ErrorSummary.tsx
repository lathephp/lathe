import { useTranslation } from '@lathephp/core';
import { forwardRef } from 'react';
import type { FormFieldData } from '../fields/types';

/** Moves focus to a field's control, and brings it into view. */
export function goToField(id: string) {
  const control = document.getElementById(id);

  control?.focus({ preventScroll: true });
  control?.scrollIntoView({ block: 'center' });
}

/**
 * The top of a form after a failed save: how many fields need a change, and a link to each,
 * named by its label, with the message beside it. The lines follow the form's field order.
 */
export const ErrorSummary = forwardRef<HTMLDivElement, { errors: Record<string, string>; fields: FormFieldData[]; idPrefix: string }>(function ErrorSummary({ errors, fields, idPrefix }, ref) {
  const { __ } = useTranslation();
  const messages = new Map<string, string>();

  for (const [key, message] of Object.entries(errors)) {
    const attribute = key.split('.')[0] ?? key;

    if (!messages.has(attribute)) {
      messages.set(attribute, message);
    }
  }

  if (messages.size === 0) {
    return null;
  }

  const known = fields.filter((field) => messages.has(field.attribute)).map((field) => field.attribute);
  const attributes = [...known, ...[...messages.keys()].filter((attribute) => !known.includes(attribute))];
  const count = attributes.length;

  return (
    <div ref={ref} role="alert" className="rounded-lg border border-destructive bg-destructive-subtle p-4 text-sm text-destructive-subtle-foreground">
      <p className="font-medium">{count === 1 ? __('1 field needs a change before you can save') : __(':count fields need a change before you can save', { count })}</p>
      <ul className="mt-2 list-disc space-y-1 ps-5">
        {attributes.map((attribute) => {
          const id = `${idPrefix}-${attribute}`;

          return (
            <li key={attribute}>
              <a
                href={`#${id}`}
                className="font-medium text-destructive-subtle-foreground underline underline-offset-2"
                onClick={(event) => {
                  event.preventDefault();
                  goToField(id);
                }}
              >
                {fields.find((field) => field.attribute === attribute)?.label ?? attribute}
              </a>
              : {messages.get(attribute)}
            </li>
          );
        })}
      </ul>
    </div>
  );
});
